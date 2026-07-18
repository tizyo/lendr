<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\CollectionCase;
use App\Models\Tenant\EscalationRule;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\PromiseToPay;
use App\Models\Tenant\User;
use App\Services\CollectionAutomationService;

function collectionAdmin(): User
{
    return User::factory()->create(['role' => UserRole::BranchManager, 'is_active' => true]);
}

function overdueCollectionLoan(int $dpdDays = 10): Loan
{
    $loan = Loan::factory()->active()->create();
    LoanSchedule::create([
        'loan_id' => $loan->id,
        'instalment_number' => 1,
        'due_date' => now()->subDays($dpdDays)->toDateString(),
        'principal_due' => 500,
        'interest_due' => 50,
        'fee_due' => 0,
        'total_due' => 550,
        'principal_paid' => 0,
        'interest_paid' => 0,
        'fee_paid' => 0,
        'penalty_paid' => 0,
        'total_paid' => 0,
        'outstanding' => 550,
        'is_paid' => false,
    ]);

    return $loan;
}

function escalationRule(int $dpd, string $action = 'assign_collector'): EscalationRule
{
    return EscalationRule::create([
        'name' => "DPD {$dpd} rule",
        'dpd_threshold' => $dpd,
        'action' => $action,
        'is_active' => true,
        'sort_order' => $dpd,
    ]);
}

// ─── Escalation Rules CRUD ────────────────────────────────────────────────────

test('can create escalation rule', function () {
    $admin = collectionAdmin();

    $response = $this->actingAs($admin)
        ->postJson(route('api.v1.escalation-rules.store'), [
            'name' => 'Early Delinquency',
            'dpd_threshold' => 5,
            'action' => 'assign_collector',
        ]);

    $response->assertCreated();
    expect(EscalationRule::count())->toBe(1);
});

test('can list escalation rules ordered by threshold', function () {
    $admin = collectionAdmin();
    escalationRule(30, 'legal_action');
    escalationRule(5, 'assign_collector');

    $response = $this->actingAs($admin)
        ->getJson(route('api.v1.escalation-rules.index'));

    $response->assertOk();
    $thresholds = collect($response->json('data'))->pluck('dpd_threshold')->toArray();
    expect($thresholds)->toBe([5, 30]);
});

test('can update escalation rule', function () {
    $admin = collectionAdmin();
    $rule = escalationRule(5);

    $response = $this->actingAs($admin)
        ->putJson(route('api.v1.escalation-rules.update', $rule), ['dpd_threshold' => 7]);

    $response->assertOk();
    expect($rule->fresh()->dpd_threshold)->toBe(7);
});

test('can delete escalation rule', function () {
    $admin = collectionAdmin();
    $rule = escalationRule(5);

    $this->actingAs($admin)->deleteJson(route('api.v1.escalation-rules.destroy', $rule))->assertOk();
    expect(EscalationRule::count())->toBe(0);
});

// ─── Escalation Logic ─────────────────────────────────────────────────────────

test('escalation service creates collection case when dpd exceeds threshold', function () {
    escalationRule(5, 'assign_collector');
    $loan = overdueCollectionLoan(10);

    $service = app(CollectionAutomationService::class);
    $action = $service->escalate($loan);

    expect($action)->toBe('assign_collector');
    expect(CollectionCase::where('loan_id', $loan->id)->count())->toBe(1);
});

test('escalation picks highest matching rule', function () {
    escalationRule(5, 'assign_collector');
    escalationRule(15, 'field_visit');
    $loan = overdueCollectionLoan(20);

    $service = app(CollectionAutomationService::class);
    $action = $service->escalate($loan);

    expect($action)->toBe('field_visit');
});

test('escalation does not create duplicate case for same loan', function () {
    escalationRule(5, 'assign_collector');
    $loan = overdueCollectionLoan(10);

    $service = app(CollectionAutomationService::class);
    $service->escalate($loan);
    $service->escalate($loan); // second call

    expect(CollectionCase::where('loan_id', $loan->id)->count())->toBe(1);
});

test('escalation returns null for loans with no overdue instalments', function () {
    escalationRule(5, 'assign_collector');
    $loan = Loan::factory()->active()->create();
    // No overdue schedule entries

    $service = app(CollectionAutomationService::class);
    expect($service->escalate($loan))->toBeNull();
    expect(CollectionCase::count())->toBe(0);
});

// ─── Manual Escalation Endpoint ───────────────────────────────────────────────

test('can manually escalate a loan via API', function () {
    $admin = collectionAdmin();
    escalationRule(5, 'assign_collector');
    $loan = overdueCollectionLoan(10);

    $response = $this->actingAs($admin)
        ->postJson(route('api.v1.loans.escalate', $loan));

    $response->assertOk();
    expect($response->json('data.escalated'))->toBeTrue();
});

// ─── Collection Cases ─────────────────────────────────────────────────────────

test('can list collection cases', function () {
    $admin = collectionAdmin();
    escalationRule(5);
    $loan = overdueCollectionLoan(10);
    app(CollectionAutomationService::class)->escalate($loan);

    $response = $this->actingAs($admin)
        ->getJson(route('api.v1.collection-cases.index'));

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1);
});

test('can update collection case status', function () {
    $admin = collectionAdmin();
    escalationRule(5);
    $loan = overdueCollectionLoan(10);
    $case = CollectionCase::create([
        'loan_id' => $loan->id,
        'borrower_id' => $loan->borrower_id,
        'status' => 'open',
        'dpd_at_creation' => 10,
    ]);

    $response = $this->actingAs($admin)
        ->putJson(route('api.v1.collection-cases.update', $case), ['status' => 'closed']);

    $response->assertOk();
    expect($case->fresh()->status)->toBe('closed');
});

// ─── Promise-to-Pay ───────────────────────────────────────────────────────────

test('can record a promise-to-pay on a collection case', function () {
    $admin = collectionAdmin();
    $loan = overdueCollectionLoan(10);
    $case = CollectionCase::create([
        'loan_id' => $loan->id,
        'borrower_id' => $loan->borrower_id,
        'status' => 'open',
        'dpd_at_creation' => 10,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('api.v1.collection-cases.promises.store', $case), [
            'promise_date' => now()->addDays(7)->toDateString(),
            'promise_amount' => 500.00,
        ]);

    $response->assertCreated();
    expect(PromiseToPay::where('collection_case_id', $case->id)->count())->toBe(1);
    expect($case->fresh()->status)->toBe('promised');
});

test('evaluate promises marks overdue pending promises as broken', function () {
    $loan = Loan::factory()->create(['status' => LoanStatus::Defaulted->value]);
    $case = CollectionCase::create([
        'loan_id' => $loan->id,
        'borrower_id' => $loan->borrower_id,
        'status' => 'promised',
        'dpd_at_creation' => 5,
    ]);
    PromiseToPay::create([
        'collection_case_id' => $case->id,
        'loan_id' => $loan->id,
        'promise_date' => now()->subDay()->toDateString(),
        'promise_amount' => 500,
        'status' => 'pending',
    ]);

    $service = app(CollectionAutomationService::class);
    $broken = $service->evaluatePromises();

    expect($broken)->toBe(1);
    expect(PromiseToPay::first()->status)->toBe('broken');
});

test('artisan command escalates and evaluates promises', function () {
    escalationRule(5, 'assign_collector');
    overdueCollectionLoan(10);

    $this->artisan('lendr:escalate-collections')->assertSuccessful();
});
