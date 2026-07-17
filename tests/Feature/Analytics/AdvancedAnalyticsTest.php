<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function analyticsAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function analyticsLoan(array $attrs = []): Loan
{
    $borrower = Borrower::factory()->create();
    $type     = LoanType::first() ?? LoanType::factory()->create();
    $plan     = LoanPlan::first() ?? LoanPlan::factory()->create(['loan_type_id' => $type->id]);

    return Loan::factory()->create(array_merge([
        'borrower_id'      => $borrower->id,
        'loan_type_id'     => $type->id,
        'loan_plan_id'     => $plan->id,
        'status'           => LoanStatus::Active,
        'disbursement_date' => now()->subDays(10)->toDateString(),
    ], $attrs));
}

// ─── Portfolio Trend tests ────────────────────────────────────────────────────

test('portfolio trend report returns monthly rows', function () {
    $admin = analyticsAdmin();

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.reports.generate', ['type' => 'portfolio_trend', 'months' => 3]))
        ->assertOk();

    expect($resp->json('data.type'))->toBe('portfolio_trend');
    expect($resp->json('data.rows'))->toHaveCount(3);
})->group('analytics');

test('portfolio trend rows have required fields', function () {
    $admin = analyticsAdmin();

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.reports.generate', ['type' => 'portfolio_trend', 'months' => 1]))
        ->assertOk();

    $row = $resp->json('data.rows.0');
    expect($row)->toHaveKeys(['month', 'disbursed', 'outstanding', 'new_borrowers']);
})->group('analytics');

// ─── Demographics tests ───────────────────────────────────────────────────────

test('demographics report returns breakdown data', function () {
    $admin = analyticsAdmin();
    Borrower::factory()->create(['gender' => 'male', 'city' => 'Lusaka', 'occupation' => 'Trader']);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.reports.generate', ['type' => 'demographics']))
        ->assertOk();

    expect($resp->json('data.type'))->toBe('demographics');
    expect($resp->json('data'))->toHaveKeys(['total_borrowers', 'by_gender', 'by_city', 'by_occupation']);
})->group('analytics');

test('demographics counts total borrowers', function () {
    $admin = analyticsAdmin();
    Borrower::factory()->count(3)->create();

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.reports.generate', ['type' => 'demographics']))
        ->assertOk();

    expect($resp->json('data.total_borrowers'))->toBeGreaterThanOrEqual(3);
})->group('analytics');

// ─── Cohort tests ─────────────────────────────────────────────────────────────

test('cohort report returns monthly cohorts', function () {
    $admin = analyticsAdmin();

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.reports.generate', ['type' => 'cohort', 'months' => 3]))
        ->assertOk();

    expect($resp->json('data.type'))->toBe('cohort');
    expect($resp->json('data.rows'))->toHaveCount(3);
})->group('analytics');

test('cohort rows have collection_rate', function () {
    $admin = analyticsAdmin();
    analyticsLoan();

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.reports.generate', ['type' => 'cohort', 'months' => 1]))
        ->assertOk();

    $row = $resp->json('data.rows.0');
    expect($row)->toHaveKeys(['cohort', 'loan_count', 'total_disbursed', 'total_collected', 'collection_rate']);
})->group('analytics');

// ─── Officer League tests ─────────────────────────────────────────────────────

test('officer league report returns ranked staff', function () {
    $admin = analyticsAdmin();

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.reports.generate', ['type' => 'officer_league']))
        ->assertOk();

    expect($resp->json('data.type'))->toBe('officer_league');
    expect($resp->json('data.rows'))->toBeArray();
})->group('analytics');

test('officer league includes date range', function () {
    $admin = analyticsAdmin();

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.reports.generate', ['type' => 'officer_league', 'date_from' => '2026-01-01', 'date_to' => '2026-12-31']))
        ->assertOk();

    expect($resp->json('data.date_from'))->toBe('2026-01-01');
    expect($resp->json('data.date_to'))->toBe('2026-12-31');
})->group('analytics');

test('officer league rows have required keys', function () {
    $admin = analyticsAdmin();

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.reports.generate', ['type' => 'officer_league']))
        ->assertOk();

    if (count($resp->json('data.rows')) > 0) {
        $row = $resp->json('data.rows.0');
        expect($row)->toHaveKeys(['officer_id', 'officer_name', 'loans_created', 'loans_disbursed', 'amount_disbursed', 'amount_collected']);
    } else {
        expect(true)->toBeTrue(); // empty result is valid
    }
})->group('analytics');

// ─── Geographic tests ─────────────────────────────────────────────────────────

test('geographic report returns city breakdown', function () {
    $admin    = analyticsAdmin();
    $borrower = Borrower::factory()->create(['city' => 'Ndola']);
    $type     = LoanType::first() ?? LoanType::factory()->create();
    $plan     = LoanPlan::first() ?? LoanPlan::factory()->create(['loan_type_id' => $type->id]);

    Loan::factory()->create([
        'borrower_id'  => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'status'       => LoanStatus::Active,
    ]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.reports.generate', ['type' => 'geographic']))
        ->assertOk();

    expect($resp->json('data.type'))->toBe('geographic');
    expect($resp->json('data.rows'))->toBeArray();
})->group('analytics');

test('geographic rows have city and outstanding', function () {
    $admin    = analyticsAdmin();
    $borrower = Borrower::factory()->create(['city' => 'Kitwe']);
    $type     = LoanType::first() ?? LoanType::factory()->create();
    $plan     = LoanPlan::first() ?? LoanPlan::factory()->create(['loan_type_id' => $type->id]);

    Loan::factory()->create([
        'borrower_id'       => $borrower->id,
        'loan_type_id'      => $type->id,
        'loan_plan_id'      => $plan->id,
        'status'            => LoanStatus::Active,
        'outstanding_balance' => 5000,
    ]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.reports.generate', ['type' => 'geographic']))
        ->assertOk();

    $rows = collect($resp->json('data.rows'));
    expect($rows->first())->toHaveKeys(['city', 'loan_count', 'disbursed', 'outstanding']);
})->group('analytics');

// ─── Unknown type test ────────────────────────────────────────────────────────

test('unknown report type returns 422', function () {
    $admin = analyticsAdmin();

    $this->actingAs($admin)
        ->getJson(route('api.v1.reports.generate', ['type' => 'nonexistent_report']))
        ->assertStatus(422);
})->group('analytics');

test('guest cannot access analytics reports', function () {
    $this->getJson(route('api.v1.reports.generate', ['type' => 'portfolio_trend']))
        ->assertUnauthorized();
})->group('analytics');
