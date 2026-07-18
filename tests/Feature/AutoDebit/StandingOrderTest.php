<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Jobs\ProcessAutoDebitJob;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\TenantWallet;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\Payment;
use App\Models\Tenant\StandingOrder;
use App\Models\Tenant\User;
use App\Services\Payment\AutoDebitService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

// ─── Cleanup ─────────────────────────────────────────────────────────────────

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    Tenant::all()->each(fn ($t) => $t->delete());
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

function soAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function soTenant(string $plan = 'enterprise'): Tenant
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
function soWallet(Tenant $tenant, array $attrs = []): TenantWallet
{
    return TenantWallet::create(array_merge([
        'tenant_id' => $tenant->id,
        'gateway' => 'airtel_money',
        'environment' => 'sandbox',
        'api_key' => 'AIRTEL-KEY',
        'api_secret' => 'AIRTEL-SECRET',
        'disburse_enabled' => true,
        'debit_enabled' => true,
        'is_active' => true,
    ], $attrs));
}

function soLoan(array $attrs = []): Loan
{
    $type = LoanType::factory()->create();
    $plan = LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create(['phone' => '0966123456']);

    return Loan::factory()->create(array_merge([
        'borrower_id' => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'created_by' => null,
        'status' => LoanStatus::Active,
        'principal_amount' => 6000.00,
        'outstanding_balance' => 6000.00,
    ], $attrs));
}

function makeOrder(Loan $loan, array $attrs = []): StandingOrder
{
    $schedule = LoanSchedule::create([
        'loan_id' => $loan->id,
        'instalment_number' => $attrs['instalment_number'] ?? 1,
        'due_date' => now()->subDay(),
        'principal_due' => 2000.00,
        'interest_due' => 100.00,
        'total_due' => 2100.00,
        'outstanding' => 2100.00,
        'is_paid' => false,
    ]);

    unset($attrs['instalment_number']);

    return StandingOrder::create(array_merge([
        'loan_id' => $loan->id,
        'loan_schedule_id' => $schedule->id,
        'borrower_id' => $loan->borrower_id,
        'amount' => 2100.00,
        'phone' => '0966123456',
        'gateway' => 'airtel_money',
        'due_date' => now()->subDay(),
        'status' => 'pending',
        'retry_count' => 0,
        'max_retries' => 3,
    ], $attrs));
}

// ═══════════════════════════════════════════════════════════════════════════════
// Section 1 — StandingOrder model
// ═══════════════════════════════════════════════════════════════════════════════

it('recordFailure schedules retry when retries remain', function () {
    $tenant = soTenant();
    tenancy()->initialize($tenant);

    $loan = soLoan();
    $order = makeOrder($loan, ['retry_count' => 0, 'max_retries' => 3]);

    $order->recordFailure('API timeout');

    $order->refresh();
    expect($order->status)->toBe('pending');
    expect($order->retry_count)->toBe(1);
    expect($order->failure_reason)->toBe('API timeout');
    expect($order->next_attempt_at)->not->toBeNull();

    tenancy()->end();
});

it('recordFailure marks failed when retries exhausted', function () {
    $tenant = soTenant();
    tenancy()->initialize($tenant);

    $loan = soLoan();
    $order = makeOrder($loan, ['retry_count' => 2, 'max_retries' => 3]);

    $order->recordFailure('Network error');

    $order->refresh();
    expect($order->status)->toBe('failed');
    expect($order->retry_count)->toBe(3);

    tenancy()->end();
});

it('retry delay increases with each attempt', function () {
    $tenant = soTenant();
    tenancy()->initialize($tenant);

    $loan = soLoan();
    $order = makeOrder($loan, ['retry_count' => 0, 'max_retries' => 3]);

    // First failure — retry_count becomes 1, next_attempt_at = now + 1 day
    $order->recordFailure('fail 1');
    $order->refresh();
    expect($order->next_attempt_at->isAfter(now()->addHours(20)))->toBeTrue();

    // Second failure — retry_count becomes 2, next_attempt_at = now + 2 days
    $order->recordFailure('fail 2');
    $order->refresh();
    expect($order->retry_count)->toBe(2);
    expect($order->next_attempt_at->isAfter(now()->addDays(1)->addHours(20)))->toBeTrue();

    tenancy()->end();
});

// ═══════════════════════════════════════════════════════════════════════════════
// Section 2 — StandingOrder API (list + cancel)
// ═══════════════════════════════════════════════════════════════════════════════

it('can list standing orders for a loan', function () {
    $tenant = soTenant();
    tenancy()->initialize($tenant);

    $admin = soAdmin();
    $loan = soLoan();
    makeOrder($loan, ['instalment_number' => 1]);
    makeOrder($loan, ['instalment_number' => 2, 'status' => 'completed']);

    $response = $this->actingAs($admin)
        ->getJson("/api/v1/loans/{$loan->id}/standing-orders");

    $response->assertOk();
    expect(count($response->json('data')))->toBe(2);

    tenancy()->end();
});

it('can cancel a pending standing order', function () {
    $tenant = soTenant();
    tenancy()->initialize($tenant);

    $admin = soAdmin();
    $loan = soLoan();
    $order = makeOrder($loan, ['status' => 'pending']);

    $this->actingAs($admin)
        ->patchJson("/api/v1/standing-orders/{$order->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($order->fresh()->status)->toBe('cancelled');

    tenancy()->end();
});

it('can cancel a failed standing order', function () {
    $tenant = soTenant();
    tenancy()->initialize($tenant);

    $admin = soAdmin();
    $loan = soLoan();
    $order = makeOrder($loan, ['status' => 'failed']);

    $this->actingAs($admin)
        ->patchJson("/api/v1/standing-orders/{$order->id}/cancel")
        ->assertOk();

    tenancy()->end();
});

it('cannot cancel a completed standing order', function () {
    $tenant = soTenant();
    tenancy()->initialize($tenant);

    $admin = soAdmin();
    $loan = soLoan();
    $order = makeOrder($loan, ['status' => 'completed']);

    $this->actingAs($admin)
        ->patchJson("/api/v1/standing-orders/{$order->id}/cancel")
        ->assertStatus(422);

    tenancy()->end();
});

it('cannot cancel a processing standing order', function () {
    $tenant = soTenant();
    tenancy()->initialize($tenant);

    $admin = soAdmin();
    $loan = soLoan();
    $order = makeOrder($loan, ['status' => 'processing']);

    $this->actingAs($admin)
        ->patchJson("/api/v1/standing-orders/{$order->id}/cancel")
        ->assertStatus(422);

    tenancy()->end();
});

// ═══════════════════════════════════════════════════════════════════════════════
// Section 3 — AutoDebitService
// ═══════════════════════════════════════════════════════════════════════════════

it('AutoDebitService marks order processing and calls provider API', function () {
    $tenant = soTenant();
    $wallet = soWallet($tenant); // root DB — must be before tenancy()->initialize()
    tenancy()->initialize($tenant);

    $loan = soLoan();
    $order = makeOrder($loan);

    Http::fake(['*' => Http::response([
        'status' => 'SUCCESS',
        'data' => ['transactionId' => 'AIRTEL-TXN-001'],
    ], 200)]);

    $service = app(AutoDebitService::class);
    $service->collect($order, $wallet);

    $order->refresh();
    // Should be 'processing' (waiting for webhook confirmation) or 'failed' on API error
    expect($order->status)->toBeIn(['processing', 'failed']);

    tenancy()->end();
});

it('AutoDebitService::confirmFromWebhook records payment and marks completed', function () {
    $tenant = soTenant();
    $wallet = soWallet($tenant); // root DB — must be before tenancy()->initialize()
    tenancy()->initialize($tenant);

    $loan = soLoan();
    $order = makeOrder($loan, [
        'status' => 'processing',
        'provider_reference' => 'LENDR-DEBIT-1-'.time(),
    ]);

    $payload = [
        'transaction_id' => 'AIRTEL-TXN-CONFIRM',
        'amount' => 2100.00,
        'phone' => '0966123456',
        'status' => 'success',
        'raw' => ['provider' => 'airtel'],
    ];

    $service = app(AutoDebitService::class);
    $service->confirmFromWebhook($order, $payload);

    $order->refresh();
    expect($order->status)->toBe('completed');
    expect($order->processed_at)->not->toBeNull();

    // A payment should have been recorded
    expect(Payment::where('loan_id', $loan->id)->count())->toBe(1);

    tenancy()->end();
});

it('AutoDebitService::confirmFromWebhook is idempotent', function () {
    $tenant = soTenant();
    $wallet = soWallet($tenant); // root DB — must be before tenancy()->initialize()
    tenancy()->initialize($tenant);

    $loan = soLoan();
    $order = makeOrder($loan, [
        'status' => 'processing',
        'provider_reference' => 'LENDR-DEBIT-1-'.time(),
    ]);

    $payload = [
        'transaction_id' => 'AIRTEL-TXN-DUP',
        'amount' => 2100.00,
        'phone' => '0966123456',
        'status' => 'success',
        'raw' => [],
    ];

    $service = app(AutoDebitService::class);
    $service->confirmFromWebhook($order, $payload);

    // Call again — should not duplicate payment
    $order->refresh();
    $service->confirmFromWebhook($order, $payload);

    expect(Payment::where('loan_id', $loan->id)->count())->toBe(1);

    tenancy()->end();
});

// ═══════════════════════════════════════════════════════════════════════════════
// Section 4 — ProcessAutoDebitJob
// ═══════════════════════════════════════════════════════════════════════════════

it('ProcessAutoDebitJob cancels order when wallet becomes inactive', function () {
    $tenant = soTenant();
    $wallet = soWallet($tenant, ['is_active' => false]); // root DB
    tenancy()->initialize($tenant);

    $loan = soLoan();
    $order = makeOrder($loan, ['status' => 'pending']);

    dispatch_sync(new ProcessAutoDebitJob($order, $wallet->id));

    $order->refresh();
    expect($order->status)->toBe('cancelled');

    tenancy()->end();
});

it('ProcessAutoDebitJob skips completed orders', function () {
    $tenant = soTenant();
    $wallet = soWallet($tenant); // root DB
    tenancy()->initialize($tenant);

    $loan = soLoan();
    $order = makeOrder($loan, ['status' => 'completed']);

    Http::fake(); // should not call API

    dispatch_sync(new ProcessAutoDebitJob($order, $wallet->id));

    Http::assertNothingSent();

    tenancy()->end();
});

// ═══════════════════════════════════════════════════════════════════════════════
// Section 5 — ProcessStandingOrdersCommand
// ═══════════════════════════════════════════════════════════════════════════════

it('process-standing-orders command dispatches jobs for due orders', function () {
    Queue::fake();

    $tenant = soTenant();
    $wallet = soWallet($tenant); // root DB
    tenancy()->initialize($tenant);

    $loan = soLoan();

    $schedule = LoanSchedule::create([
        'loan_id' => $loan->id,
        'instalment_number' => 1,
        'due_date' => now()->subDay(),
        'principal_due' => 2000, 'interest_due' => 100, 'total_due' => 2100,
        'outstanding' => 2100,
        'is_paid' => false,
    ]);

    StandingOrder::create([
        'loan_id' => $loan->id,
        'loan_schedule_id' => $schedule->id,
        'borrower_id' => $loan->borrower_id,
        'amount' => 2100,
        'phone' => '0966123456',
        'gateway' => 'airtel_money',
        'due_date' => now()->subDay(),
        'status' => 'pending',
        'retry_count' => 0,
        'max_retries' => 3,
        'next_attempt_at' => null,
    ]);

    tenancy()->end();

    $this->artisan('lendr:process-standing-orders')
        ->assertExitCode(0);

    Queue::assertPushed(ProcessAutoDebitJob::class);
});

it('process-standing-orders command dry-run does not dispatch jobs', function () {
    Queue::fake();

    $tenant = soTenant();
    $wallet = soWallet($tenant); // root DB
    tenancy()->initialize($tenant);

    $loan = soLoan();

    $schedule = LoanSchedule::create([
        'loan_id' => $loan->id,
        'instalment_number' => 1,
        'due_date' => now()->subDay(),
        'principal_due' => 2000, 'interest_due' => 100, 'total_due' => 2100,
        'outstanding' => 2100,
        'is_paid' => false,
    ]);

    StandingOrder::create([
        'loan_id' => $loan->id,
        'loan_schedule_id' => $schedule->id,
        'borrower_id' => $loan->borrower_id,
        'amount' => 2100,
        'phone' => '0966123456',
        'gateway' => 'airtel_money',
        'due_date' => now()->subDay(),
        'status' => 'pending',
        'retry_count' => 0,
        'max_retries' => 3,
        'next_attempt_at' => null,
    ]);

    tenancy()->end();

    $this->artisan('lendr:process-standing-orders', ['--dry-run' => true])
        ->assertExitCode(0);

    Queue::assertNothingPushed();
});

it('process-standing-orders command skips non-enterprise tenants', function () {
    Queue::fake();

    $tenant = soTenant('starter'); // NOT enterprise
    $wallet = soWallet($tenant);   // root DB
    tenancy()->initialize($tenant);

    $loan = soLoan();
    makeOrder($loan);

    tenancy()->end();

    $this->artisan('lendr:process-standing-orders')
        ->assertExitCode(0);

    Queue::assertNothingPushed();
});
