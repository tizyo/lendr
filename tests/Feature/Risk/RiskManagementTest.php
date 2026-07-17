<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\RiskFlag;
use App\Models\Tenant\RiskPolicy;
use App\Models\Tenant\User;
use App\Services\RiskAssessmentService;
use Database\Factories\Tenant\LoanFactory;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function riskAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function makeRiskPolicy(array $attrs = []): RiskPolicy
{
    return RiskPolicy::create(array_merge([
        'name'       => 'Test Policy',
        'rule_type'  => 'max_active_loans',
        'value'      => '2',
        'action'     => 'warn',
        'is_active'  => true,
        'sort_order' => 0,
    ], $attrs));
}

function riskLoan(Borrower $borrower, array $attrs = []): Loan
{
    $type = LoanType::first() ?? LoanType::factory()->create();
    $plan = LoanPlan::first() ?? LoanPlan::factory()->create(['loan_type_id' => $type->id]);

    return Loan::factory()->create(array_merge([
        'borrower_id'  => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'status'       => LoanStatus::Draft,
    ], $attrs));
}

// ─── Policy CRUD tests ────────────────────────────────────────────────────────

test('can list risk policies', function () {
    $admin = riskAdmin();
    makeRiskPolicy();

    $this->actingAs($admin)
        ->getJson(route('api.v1.risk-policies.index'))
        ->assertOk()
        ->assertJsonStructure(['data']);
})->group('risk');

test('can create a risk policy', function () {
    $admin = riskAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.risk-policies.store'), [
            'name'       => 'Max 3 Active Loans',
            'rule_type'  => 'max_active_loans',
            'value'      => '3',
            'action'     => 'block',
            'is_active'  => true,
            'sort_order' => 1,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.policy.name', 'Max 3 Active Loans');

    $this->assertDatabaseHas('risk_policies', ['name' => 'Max 3 Active Loans']);
})->group('risk');

test('can update a risk policy', function () {
    $admin  = riskAdmin();
    $policy = makeRiskPolicy();

    $this->actingAs($admin)
        ->putJson(route('api.v1.risk-policies.update', $policy), ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.policy.is_active', false);
})->group('risk');

test('can delete a risk policy', function () {
    $admin  = riskAdmin();
    $policy = makeRiskPolicy();

    $this->actingAs($admin)
        ->deleteJson(route('api.v1.risk-policies.destroy', $policy))
        ->assertStatus(204);

    $this->assertDatabaseMissing('risk_policies', ['id' => $policy->id]);
})->group('risk');

test('can fetch rule types', function () {
    $admin = riskAdmin();

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.risk-policies.rule-types'))
        ->assertOk();

    expect($resp->json('data'))->toContain('max_active_loans');
})->group('risk');

// ─── Assessment tests ─────────────────────────────────────────────────────────

test('risk assessment returns pass when no policies triggered', function () {
    $admin    = riskAdmin();
    $borrower = Borrower::factory()->create(['credit_score' => 750]);
    $loan     = riskLoan($borrower, ['principal_amount' => 5000]);

    // Inactive policy should not trigger
    makeRiskPolicy(['is_active' => false, 'rule_type' => 'max_active_loans', 'value' => '0']);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loans.risk-assess', $loan))
        ->assertOk();

    expect($resp->json('data.result'))->toBe('pass');
})->group('risk');

test('risk assessment creates warn flag for triggered warn policy', function () {
    $admin    = riskAdmin();
    $borrower = Borrower::factory()->create(['credit_score' => 400]);
    $loan     = riskLoan($borrower);

    makeRiskPolicy([
        'rule_type' => 'min_credit_score',
        'value'     => '600',
        'action'    => 'warn',
    ]);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loans.risk-assess', $loan))
        ->assertOk();

    expect($resp->json('data.result'))->toBe('warn');
    expect($resp->json('data.flags'))->not->toBeEmpty();
    $this->assertDatabaseHas('risk_flags', ['loan_id' => $loan->id, 'severity' => 'warn']);
})->group('risk');

test('risk assessment returns block when block policy triggered', function () {
    $admin    = riskAdmin();
    $borrower = Borrower::factory()->create(['credit_score' => 300]);
    $loan     = riskLoan($borrower);

    makeRiskPolicy([
        'rule_type' => 'min_credit_score',
        'value'     => '500',
        'action'    => 'block',
    ]);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loans.risk-assess', $loan))
        ->assertOk();

    expect($resp->json('data.result'))->toBe('block');
})->group('risk');

test('can list risk flags for a loan', function () {
    $admin    = riskAdmin();
    $borrower = Borrower::factory()->create();
    $loan     = riskLoan($borrower);
    $policy   = makeRiskPolicy();

    RiskFlag::create([
        'loan_id'        => $loan->id,
        'risk_policy_id' => $policy->id,
        'severity'       => 'warn',
        'detail'         => 'Test flag detail',
        'overridden'     => false,
    ]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.loans.risk-flags', $loan))
        ->assertOk();

    expect($resp->json('data'))->not->toBeEmpty();
})->group('risk');

test('can override a risk flag', function () {
    $admin    = riskAdmin();
    $borrower = Borrower::factory()->create();
    $loan     = riskLoan($borrower);
    $policy   = makeRiskPolicy();

    $flag = RiskFlag::create([
        'loan_id'        => $loan->id,
        'risk_policy_id' => $policy->id,
        'severity'       => 'warn',
        'detail'         => 'Override me',
        'overridden'     => false,
    ]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.risk-flags.override', $flag), [
            'override_reason' => 'Manager approved exception',
        ])
        ->assertOk()
        ->assertJsonPath('data.flag.overridden', true);
})->group('risk');

test('cannot override an already-overridden flag', function () {
    $admin    = riskAdmin();
    $borrower = Borrower::factory()->create();
    $loan     = riskLoan($borrower);
    $policy   = makeRiskPolicy();

    $flag = RiskFlag::create([
        'loan_id'        => $loan->id,
        'risk_policy_id' => $policy->id,
        'severity'       => 'warn',
        'detail'         => 'Already overridden',
        'overridden'     => true,
        'overridden_by'  => $admin->id,
        'override_reason' => 'reason',
        'overridden_at'  => now(),
    ]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.risk-flags.override', $flag), ['override_reason' => 'Again'])
        ->assertStatus(422);
})->group('risk');
