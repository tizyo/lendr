<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Investor;
use App\Models\Tenant\InvestorAllocation;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function investorAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function makeInvestor(array $attrs = []): Investor
{
    return Investor::create(array_merge([
        'investor_number' => Investor::generateInvestorNumber(),
        'name'            => 'Acme Capital '.rand(1, 999),
        'email'           => 'investor'.rand(1000, 9999).'@example.com',
        'type'            => 'institution',
        'status'          => 'active',
    ], $attrs));
}

function investorLoan(): Loan
{
    $type     = LoanType::first() ?? LoanType::factory()->create();
    $plan     = LoanPlan::first() ?? LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create();

    return Loan::factory()->create([
        'borrower_id'      => $borrower->id,
        'loan_type_id'     => $type->id,
        'loan_plan_id'     => $plan->id,
        'status'           => LoanStatus::Active,
        'principal_amount' => 20000,
    ]);
}

function makeAllocation(Investor $investor, Loan $loan, User $admin, array $attrs = []): InvestorAllocation
{
    return $investor->allocations()->create(array_merge([
        'loan_id'          => $loan->id,
        'recorded_by'      => $admin->id,
        'allocated_amount' => 10000,
        'expected_return'  => 1500,
        'actual_return'    => 0,
        'allocation_date'  => now()->toDateString(),
        'status'           => 'active',
    ], $attrs));
}

// ─── Investor CRUD ────────────────────────────────────────────────────────────

test('can list investors', function () {
    $admin = investorAdmin();
    makeInvestor();
    makeInvestor(['type' => 'individual', 'name' => 'John Doe']);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.investors.index'))
        ->assertOk();

    expect(count($resp->json('data')))->toBeGreaterThanOrEqual(2);
});

test('can create an investor', function () {
    $admin = investorAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.investors.store'), [
            'name'  => 'Beta Fund Ltd',
            'email' => 'beta@fund.com',
            'type'  => 'institution',
        ])
        ->assertCreated();

    expect($resp->json('data.investor.name'))->toBe('Beta Fund Ltd')
        ->and($resp->json('data.investor.investor_number'))->toStartWith('INV-');

    $this->assertDatabaseHas('investors', ['email' => 'beta@fund.com']);
});

test('investor number is auto-generated sequentially', function () {
    $admin = investorAdmin();
    $inv1  = makeInvestor();
    $inv2  = makeInvestor();

    $num1 = (int) substr($inv1->investor_number, 4);
    $num2 = (int) substr($inv2->investor_number, 4);

    expect($num2)->toBe($num1 + 1);
});

test('email must be unique', function () {
    $admin = investorAdmin();
    makeInvestor(['email' => 'dup@test.com']);

    $this->actingAs($admin)
        ->postJson(route('api.v1.investors.store'), [
            'name'  => 'Another',
            'email' => 'dup@test.com',
            'type'  => 'individual',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('can show investor with totals', function () {
    $admin    = investorAdmin();
    $investor = makeInvestor();
    $loan     = investorLoan();
    makeAllocation($investor, $loan, $admin, ['allocated_amount' => 5000, 'actual_return' => 300]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.investors.show', $investor))
        ->assertOk();

    expect((float) $resp->json('data.investor.total_allocated'))->toBe(5000.0)
        ->and((float) $resp->json('data.investor.total_returns'))->toBe(300.0);
});

test('can update investor status', function () {
    $admin    = investorAdmin();
    $investor = makeInvestor();

    $resp = $this->actingAs($admin)
        ->putJson(route('api.v1.investors.update', $investor), [
            'status' => 'suspended',
        ])
        ->assertOk();

    expect($resp->json('data.investor.status'))->toBe('suspended');
});

test('can delete an investor', function () {
    $admin    = investorAdmin();
    $investor = makeInvestor();

    $this->actingAs($admin)
        ->deleteJson(route('api.v1.investors.destroy', $investor))
        ->assertOk();

    $this->assertSoftDeleted('investors', ['id' => $investor->id]);
});

// ─── Allocation Tests ─────────────────────────────────────────────────────────

test('can allocate capital to a loan', function () {
    $admin    = investorAdmin();
    $investor = makeInvestor();
    $loan     = investorLoan();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.investors.allocate', $investor), [
            'loan_id'          => $loan->id,
            'allocated_amount' => 15000,
            'expected_return'  => 2250,
            'allocation_date'  => now()->toDateString(),
        ])
        ->assertCreated();

    expect((float) $resp->json('data.allocation.allocated_amount'))->toBe(15000.0)
        ->and($resp->json('data.allocation.status'))->toBe('active');

    $this->assertDatabaseHas('investor_allocations', [
        'investor_id'      => $investor->id,
        'loan_id'          => $loan->id,
        'allocated_amount' => 15000,
    ]);
});

test('can list allocations for an investor', function () {
    $admin    = investorAdmin();
    $investor = makeInvestor();
    $loan1    = investorLoan();
    $loan2    = investorLoan();

    makeAllocation($investor, $loan1, $admin);
    makeAllocation($investor, $loan2, $admin);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.investors.allocations', $investor))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(2);
});

test('can settle an allocation', function () {
    $admin      = investorAdmin();
    $investor   = makeInvestor();
    $loan       = investorLoan();
    $allocation = makeAllocation($investor, $loan, $admin);

    $resp = $this->actingAs($admin)
        ->putJson(route('api.v1.investor-allocations.update', $allocation), [
            'status'        => 'settled',
            'actual_return' => 1200,
            'settled_date'  => now()->toDateString(),
        ])
        ->assertOk();

    expect($resp->json('data.allocation.status'))->toBe('settled')
        ->and((float) $resp->json('data.allocation.actual_return'))->toBe(1200.0);
});

// ─── Portfolio Dashboard ──────────────────────────────────────────────────────

test('portfolio returns aggregate metrics', function () {
    $admin = investorAdmin();
    $inv1  = makeInvestor();
    $inv2  = makeInvestor();
    $loan  = investorLoan();

    makeAllocation($inv1, $loan, $admin, ['allocated_amount' => 10000, 'actual_return' => 500]);
    makeAllocation($inv2, $loan, $admin, ['allocated_amount' => 5000,  'actual_return' => 200]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.investors.portfolio'))
        ->assertOk();

    expect((float) $resp->json('data.total_deployed'))->toBe(15000.0)
        ->and((float) $resp->json('data.total_returns'))->toBe(700.0)
        ->and($resp->json('data.investor_count'))->toBeGreaterThanOrEqual(2);
});

test('unauthenticated cannot access investor endpoints', function () {
    $this->getJson(route('api.v1.investors.index'))
        ->assertUnauthorized();
});
