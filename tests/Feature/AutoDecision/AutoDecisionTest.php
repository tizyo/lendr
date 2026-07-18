<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\AutoDecision;
use App\Models\Tenant\AutoDecisionRule;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\User;
use App\Services\AutoDecisionService;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function decisionAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function decisionLoan(array $attrs = []): Loan
{
    $borrower = Borrower::factory()->create(['credit_score' => $attrs['credit_score'] ?? 650]);
    unset($attrs['credit_score']);

    return Loan::factory()->create(array_merge([
        'borrower_id' => $borrower->id,
        'principal_amount' => 5000,
        'tenure' => 12,
        'status' => LoanStatus::Submitted,
    ], $attrs));
}

function makeRule(array $attrs = []): AutoDecisionRule
{
    return AutoDecisionRule::create(array_merge([
        'name' => 'Test Rule '.rand(100, 999),
        'min_credit_score' => 600,
        'action' => 'approve',
        'priority' => 100,
        'is_active' => true,
    ], $attrs));
}

// ─── Rules CRUD ───────────────────────────────────────────────────────────────

test('can list decision rules', function () {
    $admin = decisionAdmin();
    makeRule(['name' => 'Prime Approve']);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.auto-decision.rules.index'))
        ->assertOk();

    expect($resp->json('data.rules'))->not->toBeEmpty();
});

test('can create a decision rule', function () {
    $admin = decisionAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.auto-decision.rules.store'), [
            'name' => 'High Score Auto Approve',
            'min_credit_score' => 700,
            'max_loan_amount' => 50000,
            'action' => 'approve',
            'priority' => 10,
        ])
        ->assertCreated();

    expect($resp->json('data.rule.action'))->toBe('approve')
        ->and((float) $resp->json('data.rule.min_credit_score'))->toBe(700.0);
});

test('can update a decision rule', function () {
    $admin = decisionAdmin();
    $rule = makeRule(['action' => 'manual']);

    $resp = $this->actingAs($admin)
        ->putJson(route('api.v1.auto-decision.rules.update', $rule), [
            'action' => 'decline',
            'priority' => 50,
        ])
        ->assertOk();

    expect($resp->json('data.rule.action'))->toBe('decline');
});

test('can delete a decision rule', function () {
    $admin = decisionAdmin();
    $rule = makeRule();

    $this->actingAs($admin)
        ->deleteJson(route('api.v1.auto-decision.rules.destroy', $rule))
        ->assertOk();

    expect(AutoDecisionRule::find($rule->id))->toBeNull();
});

// ─── Evaluation ───────────────────────────────────────────────────────────────

test('evaluate approves loan matching approve rule', function () {
    $admin = decisionAdmin();
    makeRule(['min_credit_score' => 600, 'action' => 'approve', 'priority' => 10]);

    $loan = decisionLoan(['credit_score' => 700]);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.auto-decision.evaluate', $loan))
        ->assertOk();

    expect($resp->json('data.decision.action'))->toBe('approve');
});

test('evaluate declines loan below credit score threshold', function () {
    $admin = decisionAdmin();
    makeRule(['min_credit_score' => 700, 'action' => 'approve', 'priority' => 10]);
    makeRule(['min_credit_score' => 0,   'action' => 'decline', 'priority' => 50]);

    $loan = decisionLoan(['credit_score' => 500]);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.auto-decision.evaluate', $loan))
        ->assertOk();

    expect($resp->json('data.decision.action'))->toBe('decline');
});

test('evaluate falls back to manual when no rule matches', function () {
    $admin = decisionAdmin();
    // Rule that only matches huge loans — our loan is 5000 principal
    makeRule(['min_credit_score' => 0, 'max_loan_amount' => 1000, 'action' => 'approve', 'priority' => 10]);

    $loan = decisionLoan(['credit_score' => 750]);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.auto-decision.evaluate', $loan))
        ->assertOk();

    expect($resp->json('data.decision.action'))->toBe('manual');
});

test('evaluate stores decision with factors', function () {
    $admin = decisionAdmin();
    makeRule(['min_credit_score' => 600, 'action' => 'approve', 'priority' => 10]);

    $loan = decisionLoan(['credit_score' => 650]);
    $this->actingAs($admin)
        ->postJson(route('api.v1.auto-decision.evaluate', $loan))
        ->assertOk();

    expect(AutoDecision::where('loan_id', $loan->id)->count())->toBe(1);
    $decision = AutoDecision::where('loan_id', $loan->id)->first();
    expect($decision->factors)->not->toBeEmpty();
});

// ─── Show & Override ──────────────────────────────────────────────────────────

test('can retrieve latest decision for a loan', function () {
    $admin = decisionAdmin();
    makeRule(['min_credit_score' => 0, 'action' => 'approve', 'priority' => 10]);

    $loan = decisionLoan();
    (new AutoDecisionService)->evaluate($loan);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.auto-decision.show', $loan))
        ->assertOk();

    expect($resp->json('data.decision.loan_id'))->toBe($loan->id);
});

test('returns 404 when no decision exists for loan', function () {
    $admin = decisionAdmin();
    $loan = decisionLoan();

    $this->actingAs($admin)
        ->getJson(route('api.v1.auto-decision.show', $loan))
        ->assertNotFound();
});

test('can override an auto decision', function () {
    $admin = decisionAdmin();
    makeRule(['min_credit_score' => 0, 'action' => 'decline', 'priority' => 10]);

    $loan = decisionLoan();
    $decision = (new AutoDecisionService)->evaluate($loan);

    expect($decision->action)->toBe('decline');

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.auto-decision.override', $decision), [
            'action' => 'approve',
            'notes' => 'Manual review passed',
        ])
        ->assertOk();

    expect($resp->json('data.decision.action'))->toBe('approve')
        ->and($resp->json('data.decision.reviewed_by'))->toBe($admin->id);
});
