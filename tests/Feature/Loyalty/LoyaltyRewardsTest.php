<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\LoyaltyTier;
use App\Models\Tenant\User;
use App\Services\LoyaltyService;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function loyaltyAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function loyaltyBorrower(): Borrower
{
    return Borrower::factory()->create(['phone' => '0972000001']);
}

function loyaltyLoan(Borrower $borrower): Loan
{
    $type = LoanType::factory()->create();
    $plan = LoanPlan::factory()->create(['loan_type_id' => $type->id]);

    return Loan::factory()->create([
        'borrower_id' => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'status' => LoanStatus::Active,
        'loan_number' => 'LN-LOYAL-001',
    ]);
}

function seedTiers(): void
{
    LoyaltyTier::create(['name' => 'Bronze',   'min_points' => 0,    'fee_discount_pct' => 0,   'is_active' => true]);
    LoyaltyTier::create(['name' => 'Silver',   'min_points' => 500,  'fee_discount_pct' => 5,   'is_active' => true]);
    LoyaltyTier::create(['name' => 'Gold',     'min_points' => 1000, 'fee_discount_pct' => 10,  'is_active' => true]);
    LoyaltyTier::create(['name' => 'Platinum', 'min_points' => 2000, 'fee_discount_pct' => 15,  'is_active' => true]);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

test('can list loyalty tiers', function () {
    seedTiers();
    $admin = loyaltyAdmin();

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.loyalty.tiers'))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(4);
    expect($resp->json('data.0.name'))->toBe('Bronze');
});

test('admin can create a loyalty tier', function () {
    $admin = loyaltyAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loyalty.tiers.upsert'), [
            'name' => 'Diamond',
            'min_points' => 5000,
            'fee_discount_pct' => 20,
        ])
        ->assertCreated();

    expect($resp->json('data.name'))->toBe('Diamond')
        ->and($resp->json('data.min_points'))->toBe(5000);
});

test('upsert tier updates existing tier by name', function () {
    seedTiers();
    $admin = loyaltyAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loyalty.tiers.upsert'), [
            'name' => 'Gold',
            'min_points' => 1200,
            'fee_discount_pct' => 12,
        ])
        ->assertCreated();

    $tier = LoyaltyTier::where('name', 'Gold')->first();
    expect($tier->min_points)->toBe(1200)
        ->and((float) $tier->fee_discount_pct)->toBe(12.0);
});

test('new borrower starts with zero points and Bronze tier', function () {
    $admin = loyaltyAdmin();
    $borrower = loyaltyBorrower();
    seedTiers();

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.loyalty.show', $borrower))
        ->assertOk();

    expect($resp->json('data.account.total_points'))->toBe(0)
        ->and($resp->json('data.account.tier'))->toBe('Bronze');
});

test('payment awards loyalty points', function () {
    seedTiers();
    $borrower = loyaltyBorrower();
    $loan = loyaltyLoan($borrower);
    $service = app(LoyaltyService::class);
    $admin = loyaltyAdmin();

    // Record a payment manually then award points
    $payment = \App\Models\Tenant\Payment::create([
        'receipt_number' => 'RCT-TEST-001',
        'loan_id' => $loan->id,
        'amount' => 1000,
        'principal_allocated' => 900,
        'interest_allocated' => 100,
        'penalty_allocated' => 0,
        'fee_allocated' => 0,
        'payment_method' => 'cash',
        'payment_date' => now()->toDateString(),
        'source' => 'manual',
    ]);

    $account = $service->awardForPayment($payment->load('loan'));

    // 1000 / 10 = 100 points
    expect($account->total_points)->toBe(100)
        ->and($account->tier)->toBe('Bronze');
});

test('tier upgrades when points cross threshold', function () {
    seedTiers();
    $borrower = loyaltyBorrower();
    $service = app(LoyaltyService::class);

    // Manually insert enough points to reach Silver (500+)
    $account = $service->getOrCreate($borrower->id);
    $account->update(['total_points' => 499]);

    // Award 10 more to push over 500
    $loan = loyaltyLoan($borrower);
    $payment = \App\Models\Tenant\Payment::create([
        'receipt_number' => 'RCT-TEST-002',
        'loan_id' => $loan->id,
        'amount' => 100, // 100 / 10 = 10 pts
        'principal_allocated' => 90,
        'interest_allocated' => 10,
        'penalty_allocated' => 0,
        'fee_allocated' => 0,
        'payment_method' => 'cash',
        'payment_date' => now()->toDateString(),
        'source' => 'manual',
    ]);

    $account = $service->awardForPayment($payment->load('loan'));

    expect($account->total_points)->toBe(509)
        ->and($account->tier)->toBe('Silver');
});

test('can redeem points from a borrower account', function () {
    seedTiers();
    $borrower = loyaltyBorrower();
    $admin = loyaltyAdmin();
    $service = app(LoyaltyService::class);

    $account = $service->getOrCreate($borrower->id);
    $account->update(['total_points' => 200]);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loyalty.redeem', $borrower), [
            'points' => 50,
            'description' => 'Fee discount redemption',
        ])
        ->assertOk();

    expect($resp->json('data.total_points'))->toBe(150);
});

test('cannot redeem more points than available', function () {
    seedTiers();
    $borrower = loyaltyBorrower();
    $admin = loyaltyAdmin();
    $service = app(LoyaltyService::class);

    $account = $service->getOrCreate($borrower->id);
    $account->update(['total_points' => 50]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loyalty.redeem', $borrower), [
            'points' => 100,
        ])
        ->assertUnprocessable();
});

test('fee discount reflects borrower tier', function () {
    seedTiers();
    $borrower = loyaltyBorrower();
    $service = app(LoyaltyService::class);

    $account = $service->getOrCreate($borrower->id);
    $account->update(['total_points' => 1000, 'tier' => 'Gold']);

    $discount = $service->feeDiscount($borrower);

    expect($discount)->toBe(10.0);
});

test('ledger shows point transactions', function () {
    seedTiers();
    $borrower = loyaltyBorrower();
    $admin = loyaltyAdmin();
    $service = app(LoyaltyService::class);

    $loan = loyaltyLoan($borrower);
    $payment = \App\Models\Tenant\Payment::create([
        'receipt_number' => 'RCT-TEST-003',
        'loan_id' => $loan->id,
        'amount' => 500,
        'principal_allocated' => 450,
        'interest_allocated' => 50,
        'penalty_allocated' => 0,
        'fee_allocated' => 0,
        'payment_method' => 'cash',
        'payment_date' => now()->toDateString(),
        'source' => 'manual',
    ]);
    $service->awardForPayment($payment->load('loan'));

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.loyalty.show', $borrower))
        ->assertOk();

    $transactions = $resp->json('data.transactions');
    expect(count($transactions))->toBeGreaterThanOrEqual(1)
        ->and($transactions[0]['type'])->toBe('earned');
});
