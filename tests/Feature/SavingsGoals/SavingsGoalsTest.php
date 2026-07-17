<?php

use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\SavingsAccount;
use App\Models\Tenant\User;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function goalsAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function targetAccount(Borrower $borrower, float $target = 5000, float $balance = 0): SavingsAccount
{
    return SavingsAccount::create([
        'borrower_id'    => $borrower->id,
        'account_number' => 'SAV-TARG-' . rand(1000, 9999),
        'type'           => 'target',
        'balance'        => $balance,
        'interest_rate'  => 5.0,
        'target_amount'  => $target,
        'status'         => 'active',
        'opened_date'    => now()->toDateString(),
    ]);
}

function fixedAccount(Borrower $borrower, float $balance = 10000, ?string $maturityDate = null): SavingsAccount
{
    return SavingsAccount::create([
        'borrower_id'    => $borrower->id,
        'account_number' => 'SAV-FIXED-' . rand(1000, 9999),
        'type'           => 'fixed',
        'balance'        => $balance,
        'interest_rate'  => 12.0,
        'maturity_date'  => $maturityDate ?? now()->subDay()->toDateString(), // already matured
        'status'         => 'active',
        'opened_date'    => now()->subMonths(12)->toDateString(),
    ]);
}

// ─── Goal Progress ────────────────────────────────────────────────────────────

test('goal progress returns correct percentage', function () {
    $admin    = goalsAdmin();
    $borrower = Borrower::factory()->create();
    $account  = targetAccount($borrower, 5000, 2500);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.savings.goal-progress', $account))
        ->assertOk();

    expect((float) $resp->json('data.progress_pct'))->toBe(50.0)
        ->and((float) $resp->json('data.remaining'))->toBe(2500.0)
        ->and($resp->json('data.achieved'))->toBeFalse();
});

test('goal progress shows achieved when balance meets target', function () {
    $admin    = goalsAdmin();
    $borrower = Borrower::factory()->create();
    $account  = targetAccount($borrower, 5000, 5000);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.savings.goal-progress', $account))
        ->assertOk();

    expect($resp->json('data.achieved'))->toBeTrue()
        ->and((float) $resp->json('data.remaining'))->toBe(0.0);
});

test('goal progress returns 422 for non-target account', function () {
    $admin    = goalsAdmin();
    $borrower = Borrower::factory()->create();
    $account  = fixedAccount($borrower);

    $this->actingAs($admin)
        ->getJson(route('api.v1.savings.goal-progress', $account))
        ->assertUnprocessable();
});

// ─── Matured FDs ──────────────────────────────────────────────────────────────

test('matured endpoint lists fixed accounts past maturity date', function () {
    $admin    = goalsAdmin();
    $borrower = Borrower::factory()->create();
    fixedAccount($borrower, 10000, now()->subDay()->toDateString());

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.savings.matured'))
        ->assertOk();

    expect($resp->json('data'))->not->toBeEmpty()
        ->and($resp->json('data.0.type'))->toBe('fixed');
});

test('matured endpoint excludes future maturity dates', function () {
    $admin    = goalsAdmin();
    $borrower = Borrower::factory()->create();
    SavingsAccount::create([
        'borrower_id'    => $borrower->id,
        'account_number' => 'SAV-FUT-001',
        'type'           => 'fixed',
        'balance'        => 5000,
        'interest_rate'  => 10,
        'maturity_date'  => now()->addMonths(3)->toDateString(),
        'status'         => 'active',
        'opened_date'    => now()->toDateString(),
    ]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.savings.matured'))
        ->assertOk();

    expect($resp->json('data'))->toBeEmpty();
});

// ─── FD Maturity Processing ───────────────────────────────────────────────────

test('can mature a fixed deposit account', function () {
    $admin    = goalsAdmin();
    $borrower = Borrower::factory()->create();
    $account  = fixedAccount($borrower);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.savings.mature', $account))
        ->assertOk();

    expect($resp->json('data.account.status'))->toBe('closed');
    expect(SavingsAccount::find($account->id)->status)->toBe('closed');
});

test('cannot mature a non-fixed account', function () {
    $admin    = goalsAdmin();
    $borrower = Borrower::factory()->create();
    $account  = targetAccount($borrower);

    $this->actingAs($admin)
        ->postJson(route('api.v1.savings.mature', $account))
        ->assertUnprocessable();
});

test('cannot mature an account with future maturity date', function () {
    $admin    = goalsAdmin();
    $borrower = Borrower::factory()->create();
    $account  = fixedAccount($borrower, 10000, now()->addMonths(6)->toDateString());

    $this->actingAs($admin)
        ->postJson(route('api.v1.savings.mature', $account))
        ->assertUnprocessable();
});

// ─── Batch Accrual Command ────────────────────────────────────────────────────

test('lendr:accrue-savings accrues interest on active accounts', function () {
    $borrower = Borrower::factory()->create();
    $account  = SavingsAccount::create([
        'borrower_id'    => $borrower->id,
        'account_number' => 'SAV-CMD-001',
        'type'           => 'regular',
        'balance'        => 12000,
        'interest_rate'  => 12.0, // 1% per month = 120
        'status'         => 'active',
        'opened_date'    => now()->toDateString(),
    ]);

    $this->artisan('lendr:accrue-savings')
        ->assertSuccessful();

    $account->refresh();
    // 12000 * 12% / 12 = 120
    expect((float) $account->balance)->toBeGreaterThan(12000.0);
});

test('lendr:accrue-savings dry-run does not modify balances', function () {
    $borrower = Borrower::factory()->create();
    $account  = SavingsAccount::create([
        'borrower_id'    => $borrower->id,
        'account_number' => 'SAV-DRY-001',
        'type'           => 'regular',
        'balance'        => 10000,
        'interest_rate'  => 12.0,
        'status'         => 'active',
        'opened_date'    => now()->toDateString(),
    ]);

    $this->artisan('lendr:accrue-savings', ['--dry-run' => true])
        ->assertSuccessful();

    $account->refresh();
    expect((float) $account->balance)->toBe(10000.0); // unchanged
});

test('lendr:accrue-savings skips zero-rate accounts', function () {
    $borrower = Borrower::factory()->create();
    $account  = SavingsAccount::create([
        'borrower_id'    => $borrower->id,
        'account_number' => 'SAV-ZERO-001',
        'type'           => 'regular',
        'balance'        => 5000,
        'interest_rate'  => 0,
        'status'         => 'active',
        'opened_date'    => now()->toDateString(),
    ]);

    $this->artisan('lendr:accrue-savings')->assertSuccessful();

    expect((float) $account->fresh()->balance)->toBe(5000.0);
});
