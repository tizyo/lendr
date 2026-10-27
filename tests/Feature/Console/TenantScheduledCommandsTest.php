<?php

use App\Commands\Concerns\RunsForEachTenant;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\SavingsAccount;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/** Records which tenant each run happened in; throws for slugs listed in $failFor. */
class RecordTenantRunsCommand extends Command
{
    use RunsForEachTenant;

    protected $signature = 'test:record-tenant-runs';

    public static array $seen = [];

    public static array $failFor = [];

    public function handle(): int
    {
        $slug = tenant('slug') ?? '(central)';
        self::$seen[] = $slug;

        if (in_array($slug, self::$failFor, true)) {
            throw new RuntimeException("boom in {$slug}");
        }

        return self::SUCCESS;
    }
}

beforeEach(function () {
    RecordTenantRunsCommand::$seen = [];
    RecordTenantRunsCommand::$failFor = [];
    Artisan::registerCommand(new RecordTenantRunsCommand);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    Tenant::all()->each(fn ($t) => $t->delete());
});

function scheduledTenant(string $slug, string $status, bool $provision = true): Tenant
{
    $attrs = ['id' => (string) Str::uuid(), 'name' => ucfirst($slug), 'slug' => $slug, 'plan' => 'starter', 'status' => $status];

    // Only provision (create + migrate a database for) tenants the command will actually enter.
    return $provision ? Tenant::create($attrs) : Tenant::withoutEvents(fn () => Tenant::create($attrs));
}

// ─── RunsForEachTenant ───────────────────────────────────────────────────────

it('runs tenant commands once inside every active and trial tenant', function () {
    config(['tenancy.commands_in_current_database' => false]);
    scheduledTenant('alpha', 'active');
    scheduledTenant('bravo', 'trial');
    scheduledTenant('paused', 'suspended', provision: false);

    $this->artisan('test:record-tenant-runs')->assertSuccessful();

    expect(RecordTenantRunsCommand::$seen)->toBe(['alpha', 'bravo'])
        ->and(tenancy()->initialized)->toBeFalse();
});

it('keeps going when one tenant fails, and reports failure overall', function () {
    config(['tenancy.commands_in_current_database' => false]);
    scheduledTenant('alpha', 'active');
    scheduledTenant('bravo', 'active');
    RecordTenantRunsCommand::$failFor = ['alpha'];

    $this->artisan('test:record-tenant-runs')->assertFailed();

    expect(RecordTenantRunsCommand::$seen)->toBe(['alpha', 'bravo']);
});

it('runs once in the current database when configured (test suite mode)', function () {
    config(['tenancy.commands_in_current_database' => true]);

    $this->artisan('test:record-tenant-runs')->assertSuccessful();

    expect(RecordTenantRunsCommand::$seen)->toBe(['(central)']);
});

// ─── Schedule ────────────────────────────────────────────────────────────────

it('schedules every tenant job, and not the duplicate penalty command', function () {
    $commands = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command)->implode("\n");

    foreach ([
        'lendr:process-overdue', 'lendr:payment-reminders', 'lendr:send-statements', 'lendr:run-reconciliation',
        'lendr:accrue-interest', 'lendr:expire-kyc-documents', 'lendr:process-standing-orders',
        'lendr:compliance-reminders', 'lendr:crm-followups', 'lendr:escalate-collections',
        'lendr:process-campaigns', 'lendr:accrue-savings',
    ] as $command) {
        expect($commands)->toContain($command);
    }

    expect($commands)->not->toContain('lendr:apply-penalties');
});

// ─── Savings interest idempotency ────────────────────────────────────────────

it('pays savings interest at most once per account per month', function () {
    $account = SavingsAccount::create([
        'borrower_id' => Borrower::factory()->create()->id,
        'account_number' => 'SAV-ONCE-001',
        'type' => 'regular',
        'balance' => 12000,
        'interest_rate' => 12.0,
        'status' => 'active',
        'opened_date' => now()->toDateString(),
    ]);

    $this->artisan('lendr:accrue-savings')->assertSuccessful();
    $this->artisan('lendr:accrue-savings')->assertSuccessful();

    expect((float) $account->fresh()->balance)->toBe(12120.0)
        ->and($account->transactions()->where('type', 'interest')->count())->toBe(1);
});
