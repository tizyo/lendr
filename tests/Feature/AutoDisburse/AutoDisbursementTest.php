<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Jobs\CreateStandingOrdersJob;
use App\Models\Landlord\LandlordUser;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\TenantWallet;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\DisbursementLog;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\StandingOrder;
use App\Models\Tenant\User;
use App\Services\Payment\AutoDisbursementService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

// ─── Cleanup ─────────────────────────────────────────────────────────────────

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    Tenant::all()->each(fn ($t) => $t->delete());
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

function disbAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function disbTenant(string $plan = 'enterprise'): Tenant
{
    $tenant = Tenant::create([
        'id' => (string) Str::uuid(),
        'name' => 'MFI '.uniqid(),
        'slug' => 'mfi-'.uniqid(),
        'plan' => $plan,
        'status' => 'active',
        'currency' => 'ZMW',
        'timezone' => 'Africa/Lusaka',
    ]);

    return $tenant->refresh();
}

/**
 * IMPORTANT: TenantWallet is a root-DB model.
 * Always call this BEFORE tenancy()->initialize() to avoid writing to the tenant DB.
 */
function disbWallet(Tenant $tenant, array $attrs = []): TenantWallet
{
    return TenantWallet::create(array_merge([
        'tenant_id' => $tenant->id,
        'gateway' => 'flutterwave',
        'environment' => 'sandbox',
        'api_key' => 'FLW-TEST-KEY',
        'api_secret' => 'FLW-SECRET',
        'disburse_enabled' => true,
        'debit_enabled' => true,
        'is_active' => true,
    ], $attrs));
}

function disbLoan(array $attrs = []): Loan
{
    $type = LoanType::factory()->create();
    $plan = LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create(['phone' => '0971234567']);

    return Loan::factory()->create(array_merge([
        'borrower_id' => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'created_by' => null,
        'status' => LoanStatus::Active,
        'principal_amount' => 5000.00,
        'outstanding_balance' => 5000.00,
        'disbursement_account' => '0971234567',
    ], $attrs));
}

function disbSchedule(Loan $loan, int $count = 3): void
{
    for ($i = 1; $i <= $count; $i++) {
        LoanSchedule::create([
            'loan_id' => $loan->id,
            'instalment_number' => $i,
            'due_date' => now()->addMonths($i),
            'principal_due' => 1666.67,
            'interest_due' => 100.00,
            'total_due' => 1766.67,
            'outstanding' => 1766.67,
            'is_paid' => false,
        ]);
    }
}

function landlordActorDisb(): LandlordUser
{
    return LandlordUser::factory()->create();
}

// ═══════════════════════════════════════════════════════════════════════════════
// Section 1 — TenantWallet CRUD (Landlord API)
// ═══════════════════════════════════════════════════════════════════════════════

it('landlord can create a wallet for an enterprise tenant', function () {
    $landlord = landlordActorDisb();
    $tenant = disbTenant();

    $response = $this->actingAs($landlord, 'sanctum')
        ->putJson("/api/v1/landlord/tenants/{$tenant->id}/wallet", [
            'gateway' => 'mtn_momo',
            'environment' => 'sandbox',
            'api_key' => 'MTN-API-KEY-123',
            'api_secret' => 'MTN-SECRET-456',
            'webhook_secret' => 'WEBHOOK-SECRET',
            'disburse_enabled' => true,
            'debit_enabled' => true,
            'is_active' => true,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.gateway', 'mtn_momo')
        ->assertJsonPath('data.api_key_set', true)
        ->assertJsonPath('data.api_secret_set', true)
        ->assertJsonPath('data.disburse_enabled', true)
        ->assertJsonPath('data.debit_enabled', true);

    expect(TenantWallet::where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

it('landlord can view a wallet config', function () {
    $landlord = landlordActorDisb();
    $tenant = disbTenant();
    disbWallet($tenant, ['gateway' => 'airtel_money']);

    $response = $this->actingAs($landlord, 'sanctum')
        ->getJson("/api/v1/landlord/tenants/{$tenant->id}/wallet");

    $response->assertOk()
        ->assertJsonPath('data.gateway', 'airtel_money')
        ->assertJsonMissing(['api_key' => 'FLW-TEST-KEY']); // raw key never returned
});

it('landlord gets null when no wallet configured', function () {
    $landlord = landlordActorDisb();
    $tenant = disbTenant();

    $response = $this->actingAs($landlord, 'sanctum')
        ->getJson("/api/v1/landlord/tenants/{$tenant->id}/wallet");

    $response->assertOk()->assertJsonPath('data', null);
});

it('landlord can delete a wallet', function () {
    $landlord = landlordActorDisb();
    $tenant = disbTenant();
    disbWallet($tenant);

    $this->actingAs($landlord, 'sanctum')
        ->deleteJson("/api/v1/landlord/tenants/{$tenant->id}/wallet")
        ->assertOk();

    expect(TenantWallet::where('tenant_id', $tenant->id)->exists())->toBeFalse();
});

it('wallet validation rejects unknown gateway', function () {
    $landlord = landlordActorDisb();
    $tenant = disbTenant();

    $this->actingAs($landlord, 'sanctum')
        ->putJson("/api/v1/landlord/tenants/{$tenant->id}/wallet", [
            'gateway' => 'unknown_gateway',
            'environment' => 'sandbox',
            'api_key' => 'KEY',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('gateway');
});

// ═══════════════════════════════════════════════════════════════════════════════
// Section 2 — AutoDisbursementService
// ═══════════════════════════════════════════════════════════════════════════════

it('AutoDisbursementService creates a DisbursementLog and calls Flutterwave API', function () {
    $tenant = disbTenant();
    $wallet = disbWallet($tenant); // root DB — must be before tenancy()->initialize()
    tenancy()->initialize($tenant);

    $loan = disbLoan();

    Http::fake(['*' => Http::response([
        'status' => 'success',
        'data' => ['id' => 'FLW-TX-001', 'status' => 'NEW'],
    ], 200)]);

    $service = app(AutoDisbursementService::class);
    $log = $service->disburse($loan, $wallet);

    expect($log)->toBeInstanceOf(DisbursementLog::class);
    expect($log->loan_id)->toBe($loan->id);
    expect($log->gateway)->toBe('flutterwave');
    expect($log->status)->toBeIn(['processing', 'completed', 'failed', 'initiated']);
    expect(DisbursementLog::where('loan_id', $loan->id)->count())->toBe(1);

    tenancy()->end();
});

it('AutoDisbursementService marks log failed on HTTP error', function () {
    $tenant = disbTenant();
    $wallet = disbWallet($tenant); // root DB — must be before tenancy()->initialize()
    tenancy()->initialize($tenant);

    $loan = disbLoan();

    Http::fake(['*' => Http::response(['message' => 'Bad credentials'], 401)]);

    $service = app(AutoDisbursementService::class);
    $log = $service->disburse($loan, $wallet);

    expect($log->status)->toBe('failed');

    tenancy()->end();
});

// ═══════════════════════════════════════════════════════════════════════════════
// Section 3 — CreateStandingOrdersJob
// ═══════════════════════════════════════════════════════════════════════════════

it('CreateStandingOrdersJob creates one StandingOrder per unpaid instalment', function () {
    $tenant = disbTenant();
    $wallet = disbWallet($tenant); // root DB — must be before tenancy()->initialize()
    tenancy()->initialize($tenant);

    $loan = disbLoan();
    disbSchedule($loan, 3);

    dispatch_sync(new CreateStandingOrdersJob($loan, $wallet->id));

    expect(StandingOrder::where('loan_id', $loan->id)->count())->toBe(3);
    $order = StandingOrder::where('loan_id', $loan->id)->first();
    expect($order->gateway)->toBe('flutterwave');
    expect($order->status)->toBe('pending');
    expect($order->phone)->toBe('0971234567');

    tenancy()->end();
});

it('CreateStandingOrdersJob skips already existing standing orders', function () {
    $tenant = disbTenant();
    $wallet = disbWallet($tenant); // root DB — must be before tenancy()->initialize()
    tenancy()->initialize($tenant);

    $loan = disbLoan();
    disbSchedule($loan, 2);

    // First run
    dispatch_sync(new CreateStandingOrdersJob($loan, $wallet->id));
    expect(StandingOrder::where('loan_id', $loan->id)->count())->toBe(2);

    // Second run — should be idempotent
    dispatch_sync(new CreateStandingOrdersJob($loan, $wallet->id));
    expect(StandingOrder::where('loan_id', $loan->id)->count())->toBe(2);

    tenancy()->end();
});

it('CreateStandingOrdersJob skips paid instalments', function () {
    $tenant = disbTenant();
    $wallet = disbWallet($tenant); // root DB — must be before tenancy()->initialize()
    tenancy()->initialize($tenant);

    $loan = disbLoan();

    // 3 instalments, first already paid
    LoanSchedule::create([
        'loan_id' => $loan->id, 'instalment_number' => 1,
        'due_date' => now()->subMonth(), 'principal_due' => 1666.67,
        'interest_due' => 100, 'total_due' => 1766.67, 'outstanding' => 0, 'is_paid' => true,
    ]);
    LoanSchedule::create([
        'loan_id' => $loan->id, 'instalment_number' => 2,
        'due_date' => now()->addMonth(), 'principal_due' => 1666.67,
        'interest_due' => 100, 'total_due' => 1766.67, 'outstanding' => 1766.67, 'is_paid' => false,
    ]);
    LoanSchedule::create([
        'loan_id' => $loan->id, 'instalment_number' => 3,
        'due_date' => now()->addMonths(2), 'principal_due' => 1666.67,
        'interest_due' => 100, 'total_due' => 1766.67, 'outstanding' => 1766.67, 'is_paid' => false,
    ]);

    dispatch_sync(new CreateStandingOrdersJob($loan, $wallet->id));

    expect(StandingOrder::where('loan_id', $loan->id)->count())->toBe(2);

    tenancy()->end();
});

it('CreateStandingOrdersJob aborts if wallet is inactive', function () {
    $tenant = disbTenant();
    $wallet = disbWallet($tenant, ['is_active' => false]); // root DB
    tenancy()->initialize($tenant);

    $loan = disbLoan();
    disbSchedule($loan, 2);

    dispatch_sync(new CreateStandingOrdersJob($loan, $wallet->id));

    expect(StandingOrder::where('loan_id', $loan->id)->count())->toBe(0);

    tenancy()->end();
});
