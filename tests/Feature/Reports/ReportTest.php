<?php

use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Expense;
use App\Models\Tenant\ExpenseCategory;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;

function reportUser(): User
{
    return User::factory()->create(['role' => UserRole::Accountant, 'is_active' => true]);
}

// ─── Loans Report ─────────────────────────────────────────────────────────────

test('loans report returns correct structure', function () {
    $user = reportUser();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.reports.generate', 'loans'));

    $response->assertOk()
             ->assertJsonStructure(['data' => ['type', 'total', 'summary', 'rows']]);

    expect($response->json('data.type'))->toBe('loans');
});

test('loans report rows include required fields', function () {
    $user     = reportUser();
    $loanType = LoanType::factory()->create();
    $plan     = LoanPlan::factory()->create(['loan_type_id' => $loanType->id]);
    $borrower = Borrower::factory()->create();

    Loan::factory()->create([
        'loan_type_id' => $loanType->id,
        'loan_plan_id' => $plan->id,
        'borrower_id'  => $borrower->id,
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.reports.generate', 'loans'));

    $row = $response->json('data.rows.0');
    expect($row)->toHaveKeys([
        'loan_number', 'borrower_name', 'principal_amount', 'status', 'application_date',
    ]);
});

test('loans report can be filtered by status', function () {
    $user     = reportUser();
    $loanType = LoanType::factory()->create();
    $plan     = LoanPlan::factory()->create(['loan_type_id' => $loanType->id]);
    $borrower = Borrower::factory()->create();

    Loan::factory()->create(['loan_type_id' => $loanType->id, 'loan_plan_id' => $plan->id, 'borrower_id' => $borrower->id, 'status' => 'draft']);
    Loan::factory()->create(['loan_type_id' => $loanType->id, 'loan_plan_id' => $plan->id, 'borrower_id' => $borrower->id, 'status' => 'submitted']);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.reports.generate', 'loans').'?status=draft');

    $statuses = collect($response->json('data.rows'))->pluck('status')->unique()->values();
    expect($statuses->toArray())->toBe(['draft']);
});

// ─── Payments Report ──────────────────────────────────────────────────────────

test('payments report returns correct structure', function () {
    $user = reportUser();

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.reports.generate', 'payments'))
        ->assertOk()
        ->assertJsonPath('data.type', 'payments')
        ->assertJsonStructure(['data' => ['summary' => ['total_collected', 'total_principal', 'total_interest']]]);
});

// ─── Expenses Report ──────────────────────────────────────────────────────────

test('expenses report returns correct structure', function () {
    $user = reportUser();

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.reports.generate', 'expenses'))
        ->assertOk()
        ->assertJsonPath('data.type', 'expenses')
        ->assertJsonStructure(['data' => ['summary' => ['total_amount', 'total_approved']]]);
});

test('expenses report sums approved amounts', function () {
    $user = reportUser();
    $cat  = ExpenseCategory::factory()->create();

    Expense::factory()->approved()->create(['expense_category_id' => $cat->id, 'amount' => 500]);
    Expense::factory()->approved()->create(['expense_category_id' => $cat->id, 'amount' => 300]);
    Expense::factory()->pending()->create(['expense_category_id' => $cat->id, 'amount' => 1000]);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.reports.generate', 'expenses'));

    expect($response->json('data.summary.total_approved'))->toBeGreaterThanOrEqual(800);
});

// ─── Borrowers Report ────────────────────────────────────────────────────────

test('borrowers report returns correct structure', function () {
    $user = reportUser();
    Borrower::factory()->count(3)->create(['is_active' => true]);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.reports.generate', 'borrowers'));

    $response->assertOk()
             ->assertJsonPath('data.type', 'borrowers');

    expect($response->json('data.rows'))->not->toBeEmpty();
    expect($response->json('data.rows.0'))->toHaveKeys(['borrower_number', 'first_name', 'last_name', 'is_active']);
});

// ─── PAR Report ───────────────────────────────────────────────────────────────

test('par report returns portfolio-at-risk structure', function () {
    $user = reportUser();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.reports.generate', 'par'));

    $response->assertOk()
             ->assertJsonPath('data.type', 'par')
             ->assertJsonStructure(['data' => ['type', 'total', 'summary' => ['total_portfolio', 'par_buckets'], 'rows']]);
});

test('par report summary includes all dpd buckets', function () {
    $user = reportUser();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.reports.generate', 'par'))
        ->assertOk();

    $buckets = $response->json('data.summary.par_buckets');
    expect($buckets)->toHaveKeys(['current', 'par1', 'par7', 'par30', 'par90']);
});

// ─── Collection Efficiency Report ─────────────────────────────────────────────

test('collection report returns monthly efficiency data', function () {
    $user = reportUser();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.reports.generate', 'collection'));

    $response->assertOk()
             ->assertJsonPath('data.type', 'collection')
             ->assertJsonStructure(['data' => ['type', 'year', 'summary' => ['total_due', 'total_collected', 'overall_efficiency'], 'rows']]);

    // 12 months
    $this->assertCount(12, $response->json('data.rows'));
});

test('collection report accepts year parameter', function () {
    $user = reportUser();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.reports.generate', ['type' => 'collection', 'year' => 2025]))
        ->assertOk();

    $this->assertSame(2025, $response->json('data.year'));
});

// ─── Error Handling ───────────────────────────────────────────────────────────

test('unknown report type returns 422', function () {
    $user = reportUser();

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.reports.generate', 'foo-bar'))
        ->assertStatus(422);
});


// ─── Export Endpoints ─────────────────────────────────────────────────────────

test('loans report can be exported as csv', function () {
    $user = reportUser();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => '*/*'])
        ->get(route('api.v1.reports.export', ['type' => 'loans', 'format' => 'csv']));

    $response->assertStatus(200);
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

test('payments report can be exported as excel', function () {
    $user = reportUser();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => '*/*'])
        ->get(route('api.v1.reports.export', ['type' => 'payments', 'format' => 'excel']));

    // Excel download returns 200 with spreadsheet content-type
    $response->assertStatus(200);
    expect($response->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('unknown type returns 422 for export endpoint', function () {
    $user = reportUser();

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.reports.export', ['type' => 'unknown-type', 'format' => 'csv']))
        ->assertStatus(422);
});
