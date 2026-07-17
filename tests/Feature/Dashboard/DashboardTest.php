<?php

use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Expense;
use App\Models\Tenant\ExpenseCategory;
use App\Models\Tenant\FundBalance;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\Payment;
use App\Models\Tenant\User;

function dashUser(): User
{
    return User::factory()->create(['role' => UserRole::BranchManager, 'is_active' => true]);
}

// ─── KPIs ─────────────────────────────────────────────────────────────────────

test('kpis endpoint returns all required fields', function () {
    $user = dashUser();

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.dashboard.kpis'))
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'active_loans', 'overdue_loans', 'total_borrowers',
            'total_outstanding', 'disbursed_month', 'collected_month',
            'expenses_month', 'par_30', 'available_balance', 'currency',
            'fund' => [
                'available_balance', 'total_deposits', 'opening_balance',
                'total_disbursed', 'total_repaid', 'total_penalties',
                'total_expenses', 'net_position', 'utilization_rate',
            ],
        ]]);
});

test('fund block reflects actual fund balance totals', function () {
    $user = dashUser();

    $balance = FundBalance::current();
    $balance->update([
        'available_balance' => 8000,
        'total_deposits'    => 10000,
        'total_disbursed'   => 5000,
        'total_repaid'      => 3000,
        'total_expenses'    => 2000,
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.dashboard.kpis'));

    $fund = $response->json('data.fund');
    expect($fund['available_balance'])->toEqual(8000);
    expect($fund['total_disbursed'])->toEqual(5000);
    expect($fund['total_repaid'])->toEqual(3000);
    expect($fund['total_expenses'])->toEqual(2000);
    expect($fund['utilization_rate'])->toEqual(50.0); // 5000 / 10000
});

test('utilization rate is 0 when no capital deposited', function () {
    $user = dashUser();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.dashboard.kpis'));

    expect($response->json('data.fund.utilization_rate'))->toEqual(0);
});

test('kpis counts reflect actual records', function () {
    $user = dashUser();

    Borrower::factory()->count(3)->create(['is_active' => true]);
    Borrower::factory()->create(['is_active' => false]);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.dashboard.kpis'));

    expect($response->json('data.total_borrowers'))->toBeGreaterThanOrEqual(3);
});

test('kpis returns zero available_balance when fund uninitialized', function () {
    $user = dashUser();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.dashboard.kpis'));

    expect($response->json('data.available_balance'))->toEqual(0);
});

// ─── Charts ───────────────────────────────────────────────────────────────────

test('disbursements chart returns 12-month series by default', function () {
    $user = dashUser();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.dashboard.charts', 'disbursements'));

    $response->assertOk();
    $series = $response->json('data.series');
    expect($series)->toHaveCount(12);
    expect($series[0])->toHaveKeys(['month', 'label', 'total', 'count']);
});

test('repayments chart returns series with correct structure', function () {
    $user = dashUser();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.dashboard.charts', 'repayments'));

    $response->assertOk();
    expect($response->json('data.type'))->toBe('repayments');
    expect($response->json('data.series'))->toHaveCount(12);
});

test('expenses chart returns series', function () {
    $user = dashUser();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.dashboard.charts', 'expenses'));

    $response->assertOk()
             ->assertJsonPath('data.type', 'expenses');
});

test('chart months parameter is respected', function () {
    $user = dashUser();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.dashboard.charts', 'disbursements').'?months=6');

    $response->assertOk();
    expect($response->json('data.series'))->toHaveCount(6);
});

test('unknown chart type returns 422', function () {
    $user = dashUser();

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.dashboard.charts', 'unknown-type'))
        ->assertStatus(422);
});

test('disbursements chart sums actual data', function () {
    $user = dashUser();

    $loanType = LoanType::factory()->create();
    $plan     = LoanPlan::factory()->create(['loan_type_id' => $loanType->id]);
    $borrower = Borrower::factory()->create();

    Loan::factory()->create([
        'loan_type_id'     => $loanType->id,
        'loan_plan_id'     => $plan->id,
        'borrower_id'      => $borrower->id,
        'status'           => 'active',
        'principal_amount' => 2000,
        'disbursement_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.dashboard.charts', 'disbursements'));

    $thisMonth = collect($response->json('data.series'))
        ->firstWhere('month', now()->format('Y-m'));

    expect($thisMonth['total'])->toBeGreaterThanOrEqual(2000);
    expect($thisMonth['count'])->toBeGreaterThanOrEqual(1);
});
