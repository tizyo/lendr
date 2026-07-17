<?php

use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\SavingsAccount;
use App\Models\Tenant\User;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function savingsAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function savingsBorrower(): Borrower
{
    return Borrower::factory()->create(['is_active' => true]);
}

function openAccount(User $admin, Borrower $borrower, array $extra = []): SavingsAccount
{
    return SavingsAccount::create(array_merge([
        'borrower_id'    => $borrower->id,
        'opened_by'      => $admin->id,
        'account_number' => SavingsAccount::generateAccountNumber(),
        'type'           => 'regular',
        'balance'        => 0,
        'interest_rate'  => 5.0,
        'status'         => 'active',
        'opened_date'    => now()->toDateString(),
    ], $extra));
}

// ─── Account tests ────────────────────────────────────────────────────────────

test('can open a new savings account', function () {
    $admin    = savingsAdmin();
    $borrower = savingsBorrower();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.savings.store'), [
            'borrower_id'   => $borrower->id,
            'type'          => 'regular',
            'interest_rate' => 5.0,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.account.type', 'regular');

    expect($resp->json('data.account.balance'))->toEqual(0);

    $this->assertDatabaseHas('savings_accounts', ['borrower_id' => $borrower->id]);
})->group('savings');

test('account number is auto-generated', function () {
    $admin    = savingsAdmin();
    $borrower = savingsBorrower();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.savings.store'), ['borrower_id' => $borrower->id, 'type' => 'regular'])
        ->assertStatus(201);

    expect($resp->json('data.account.account_number'))->toStartWith('SAV');
})->group('savings');

test('opening account requires valid borrower_id and type', function () {
    $admin = savingsAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.savings.store'), ['type' => 'regular'])
        ->assertJsonValidationErrors(['borrower_id']);

    $this->actingAs($admin)
        ->postJson(route('api.v1.savings.store'), ['borrower_id' => 9999, 'type' => 'regular'])
        ->assertJsonValidationErrors(['borrower_id']);
})->group('savings');

test('index lists savings accounts', function () {
    $admin    = savingsAdmin();
    $borrower = savingsBorrower();

    openAccount($admin, $borrower);
    openAccount($admin, $borrower);

    $this->actingAs($admin)
        ->getJson(route('api.v1.savings.index'))
        ->assertOk()
        ->assertJsonPath('meta.total', 2);
})->group('savings');

test('index filters by status', function () {
    $admin    = savingsAdmin();
    $borrower = savingsBorrower();

    openAccount($admin, $borrower, ['status' => 'active']);
    openAccount($admin, $borrower, ['status' => 'closed']);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.savings.index', ['status' => 'active']))
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
})->group('savings');

// ─── Deposit tests ────────────────────────────────────────────────────────────

test('can deposit into an active account', function () {
    $admin    = savingsAdmin();
    $borrower = savingsBorrower();
    $account  = openAccount($admin, $borrower);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.savings.deposit', $account), ['amount' => 1000])
        ->assertStatus(201)
        ->assertJsonPath('data.transaction.type', 'deposit');

    expect($resp->json('data.balance'))->toEqual(1000);
})->group('savings');

test('deposit requires positive amount', function () {
    $admin    = savingsAdmin();
    $borrower = savingsBorrower();
    $account  = openAccount($admin, $borrower);

    $this->actingAs($admin)
        ->postJson(route('api.v1.savings.deposit', $account), ['amount' => 0])
        ->assertJsonValidationErrors(['amount']);
})->group('savings');

test('cannot deposit into a closed account', function () {
    $admin    = savingsAdmin();
    $borrower = savingsBorrower();
    $account  = openAccount($admin, $borrower, ['status' => 'closed']);

    $this->actingAs($admin)
        ->postJson(route('api.v1.savings.deposit', $account), ['amount' => 500])
        ->assertStatus(422);
})->group('savings');

// ─── Withdrawal tests ─────────────────────────────────────────────────────────

test('can withdraw from account with sufficient balance', function () {
    $admin    = savingsAdmin();
    $borrower = savingsBorrower();
    $account  = openAccount($admin, $borrower, ['balance' => 2000]);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.savings.withdraw', $account), ['amount' => 500])
        ->assertStatus(201);

    expect($resp->json('data.balance'))->toEqual(1500);
})->group('savings');

test('withdrawal is rejected if balance is insufficient', function () {
    $admin    = savingsAdmin();
    $borrower = savingsBorrower();
    $account  = openAccount($admin, $borrower, ['balance' => 100]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.savings.withdraw', $account), ['amount' => 500])
        ->assertStatus(422);
})->group('savings');

// ─── Interest accrual ─────────────────────────────────────────────────────────

test('can accrue monthly interest', function () {
    $admin    = savingsAdmin();
    $borrower = savingsBorrower();
    $account  = openAccount($admin, $borrower, ['balance' => 12000, 'interest_rate' => 12.0]);

    // Monthly = 12000 * (12/100) / 12 = 120
    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.savings.accrue-interest', $account))
        ->assertOk()
        ->assertJsonPath('data.transaction.type', 'interest');

    expect((float) $resp->json('data.transaction.amount'))->toBe(120.0);
    expect((float) $resp->json('data.balance'))->toBe(12120.0);
})->group('savings');

test('interest accrual fails when balance is zero', function () {
    $admin    = savingsAdmin();
    $borrower = savingsBorrower();
    $account  = openAccount($admin, $borrower, ['balance' => 0]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.savings.accrue-interest', $account))
        ->assertStatus(422);
})->group('savings');

// ─── Statement ────────────────────────────────────────────────────────────────

test('statement returns paginated transactions', function () {
    $admin    = savingsAdmin();
    $borrower = savingsBorrower();
    $account  = openAccount($admin, $borrower);

    $this->actingAs($admin)->postJson(route('api.v1.savings.deposit', $account), ['amount' => 100]);
    $this->actingAs($admin)->postJson(route('api.v1.savings.deposit', $account), ['amount' => 200]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.savings.statement', $account))
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(2);
})->group('savings');

test('unauthenticated cannot access savings', function () {
    $this->getJson(route('api.v1.savings.index'))->assertStatus(401);
})->group('savings');
