<?php

use App\Enums\UserRole;
use App\Models\Tenant\FundBalance;
use App\Models\Tenant\FundDeposit;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;
use Spatie\Permission\Models\Permission;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function fundUser(array $permissions = []): User
{
    $user = User::factory()->create(['role' => UserRole::BranchManager, 'is_active' => true]);

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    if ($permissions) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

// ─── Balance Endpoint ─────────────────────────────────────────────────────────

test('balance endpoint returns fund balance data', function () {
    $user = fundUser();

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.funds.balance'))
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'available_balance', 'total_deposits', 'total_disbursed',
            'total_repaid', 'currency',
        ]]);
});

test('balance is initialized to zero on first access', function () {
    $user = fundUser();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.funds.balance'));

    expect($response->json('data.available_balance'))->toEqual(0);
});

// ─── Deposit CRUD ─────────────────────────────────────────────────────────────

test('a deposit can be created as pending', function () {
    $user = fundUser();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.funds.deposits.store'), [
            'amount'         => 50000,
            'source'         => 'ABC Investors Ltd',
            'payment_method' => 'bank_transfer',
            'deposit_date'   => now()->toDateString(),
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'pending');

    $this->assertDatabaseHas('fund_deposits', ['source' => 'ABC Investors Ltd']);
});

test('deposit reference follows DEP-YYYYMM-XXXXX format', function () {
    $user = fundUser();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.funds.deposits.store'), [
            'amount'         => 10000,
            'source'         => 'Test Source',
            'payment_method' => 'cash',
            'deposit_date'   => now()->toDateString(),
        ]);

    expect($response->json('data.reference'))->toMatch('/^DEP-\d{6}-\d{5}$/');
});

test('a pending deposit can be updated', function () {
    $user = fundUser();
    $deposit = FundDeposit::create([
        'reference'      => 'DEP-'.now()->format('Ym').'-00001',
        'amount'         => 5000,
        'source'         => 'Old Source',
        'payment_method' => 'cash',
        'deposit_date'   => now()->toDateString(),
        'deposited_by'   => $user->id,
        'status'         => 'pending',
    ]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->putJson(route('api.v1.funds.deposits.update', $deposit), [
            'source' => 'Updated Source',
            'amount' => 7500,
        ])
        ->assertOk()
        ->assertJsonPath('data.source', 'Updated Source');
});

test('an approved deposit cannot be edited', function () {
    $user = fundUser();
    $deposit = FundDeposit::create([
        'reference'      => 'DEP-'.now()->format('Ym').'-00002',
        'amount'         => 5000,
        'source'         => 'Some Source',
        'payment_method' => 'cash',
        'deposit_date'   => now()->toDateString(),
        'deposited_by'   => $user->id,
        'status'         => 'approved',
    ]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->putJson(route('api.v1.funds.deposits.update', $deposit), ['source' => 'Changed'])
        ->assertStatus(422);
});

// ─── Deposit Approval ─────────────────────────────────────────────────────────

test('approving a deposit credits the fund balance', function () {
    $user = fundUser(['funds.approve']);

    $deposit = FundDeposit::create([
        'reference'      => 'DEP-'.now()->format('Ym').'-00003',
        'amount'         => 25000,
        'source'         => 'Capital Partner',
        'payment_method' => 'bank_transfer',
        'deposit_date'   => now()->toDateString(),
        'deposited_by'   => $user->id,
        'status'         => 'pending',
    ]);

    $balanceBefore = (float) FundBalance::current()->available_balance;

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.funds.deposits.approve', $deposit))
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $balanceAfter = (float) FundBalance::current()->available_balance;
    expect($balanceAfter)->toBe($balanceBefore + 25000.0);
});

test('approving a deposit creates a fund transaction record', function () {
    $user = fundUser(['funds.approve']);

    $deposit = FundDeposit::create([
        'reference'      => 'DEP-'.now()->format('Ym').'-00004',
        'amount'         => 10000,
        'source'         => 'Investor X',
        'payment_method' => 'bank_transfer',
        'deposit_date'   => now()->toDateString(),
        'deposited_by'   => $user->id,
        'status'         => 'pending',
    ]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.funds.deposits.approve', $deposit));

    $this->assertDatabaseHas('fund_transactions', [
        'type'        => 'deposit',
        'amount'      => 10000,
        'source_type' => 'App\\Models\\Tenant\\FundDeposit',
        'source_id'   => $deposit->id,
    ]);
});

test('approving requires funds.approve permission', function () {
    $user = fundUser(); // no permission

    $deposit = FundDeposit::create([
        'reference'      => 'DEP-'.now()->format('Ym').'-00005',
        'amount'         => 1000,
        'source'         => 'Someone',
        'payment_method' => 'cash',
        'deposit_date'   => now()->toDateString(),
        'deposited_by'   => $user->id,
        'status'         => 'pending',
    ]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.funds.deposits.approve', $deposit))
        ->assertForbidden();
});

test('a deposit can be rejected with a reason', function () {
    $user = fundUser(['funds.approve']);

    $deposit = FundDeposit::create([
        'reference'      => 'DEP-'.now()->format('Ym').'-00006',
        'amount'         => 5000,
        'source'         => 'Dubious Source',
        'payment_method' => 'cash',
        'deposit_date'   => now()->toDateString(),
        'deposited_by'   => $user->id,
        'status'         => 'pending',
    ]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.funds.deposits.reject', $deposit), [
            'reason' => 'Unverified source of funds.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    // Rejecting must NOT change the balance
    expect((float) FundBalance::current()->available_balance)->toEqual(0.0);
});

// ─── Fund Integration ─────────────────────────────────────────────────────────

test('disbursing a loan debits the fund balance', function () {
    $user    = fundUser(['loans.disburse', 'funds.approve']);
    $type    = LoanType::factory()->create();
    $plan    = LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $loan    = Loan::factory()->approved()->create([
        'loan_plan_id'    => $plan->id,
        'loan_type_id'    => $type->id,
        'principal_amount' => 5000,
        'interest_amount'  => 1500,
        'tenure'           => 6,
    ]);

    // First fund the pool
    $deposit = FundDeposit::create([
        'reference'      => 'DEP-'.now()->format('Ym').'-00010',
        'amount'         => 20000,
        'source'         => 'Capital Pool',
        'payment_method' => 'bank_transfer',
        'deposit_date'   => now()->toDateString(),
        'deposited_by'   => $user->id,
        'status'         => 'pending',
    ]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.funds.deposits.approve', $deposit));

    $balanceBefore = (float) FundBalance::current()->available_balance;

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.loans.disburse', $loan), [
            'disbursement_method' => 'cash',
            'disbursement_date'   => now()->toDateString(),
        ]);

    $balanceAfter = (float) FundBalance::current()->available_balance;
    expect($balanceAfter)->toBe($balanceBefore - 5000.0);
});

test('recording a payment credits the fund balance', function () {
    $user = fundUser();
    $type = LoanType::factory()->create();
    $plan = LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $loan = Loan::factory()->active()->create([
        'loan_plan_id'        => $plan->id,
        'loan_type_id'        => $type->id,
        'principal_amount'    => 5000,
        'interest_amount'     => 0,
        'outstanding_balance' => 5000,
        'penalty_balance'     => 0,
    ]);

    $balanceBefore = (float) FundBalance::current()->available_balance;

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.payments.store'), [
            'loan_id'        => $loan->id,
            'amount'         => 1000,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ]);

    $balanceAfter = (float) FundBalance::current()->available_balance;
    expect($balanceAfter)->toBeGreaterThan($balanceBefore);
});

// ─── Transactions Ledger ─────────────────────────────────────────────────────

test('transaction ledger is paginated and filterable by type', function () {
    $user = fundUser(['funds.approve']);

    // Approve two deposits to generate transactions
    foreach (['00020', '00021'] as $seq) {
        $dep = FundDeposit::create([
            'reference'      => 'DEP-'.now()->format('Ym')."-{$seq}",
            'amount'         => 1000,
            'source'         => 'Test',
            'payment_method' => 'cash',
            'deposit_date'   => now()->toDateString(),
            'deposited_by'   => $user->id,
            'status'         => 'pending',
        ]);

        $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->postJson(route('api.v1.funds.deposits.approve', $dep));
    }

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.funds.transactions', ['type' => 'deposit']));

    $response->assertOk();
    expect(collect($response->json('data'))->every(fn ($t) => $t['type'] === 'deposit'))->toBeTrue();
});
