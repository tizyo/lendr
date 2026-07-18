<?php

use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\LoanOffer;
use App\Models\Tenant\LoanOfferRule;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;
use App\Services\LoanOfferService;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function offerAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function offerPlan(): LoanPlan
{
    $type = LoanType::factory()->create();

    return LoanPlan::factory()->create([
        'loan_type_id' => $type->id,
        'interest_rate' => 24.0,
        'min_tenure' => 6,
        'max_tenure' => 24,
    ]);
}

function offerBorrower(int $creditScore = 720): Borrower
{
    return Borrower::factory()->create([
        'is_active' => true,
        'credit_score' => $creditScore,
    ]);
}

function offerRule(LoanPlan $plan, array $attrs = []): LoanOfferRule
{
    return LoanOfferRule::create(array_merge([
        'name' => 'Standard Offer',
        'min_credit_score' => 650,
        'max_credit_score' => 850,
        'loan_plan_id' => $plan->id,
        'min_offered_amount' => 5000.00,
        'max_offered_amount' => 50000.00,
        'validity_days' => 30,
        'is_active' => true,
    ], $attrs));
}

// ─── Rule CRUD ────────────────────────────────────────────────────────────────

test('can create a loan offer rule', function () {
    $admin = offerAdmin();
    $plan = offerPlan();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loan-offers.rules.store'), [
            'name' => 'Gold Rule',
            'min_credit_score' => 700,
            'max_credit_score' => 850,
            'loan_plan_id' => $plan->id,
            'min_offered_amount' => 10000,
            'max_offered_amount' => 100000,
            'validity_days' => 14,
        ])
        ->assertStatus(201);

    expect($resp->json('data.rule.name'))->toBe('Gold Rule');
    $this->assertDatabaseHas('loan_offer_rules', ['name' => 'Gold Rule']);
});

test('can list offer rules', function () {
    $admin = offerAdmin();
    $plan = offerPlan();
    offerRule($plan);
    offerRule($plan, ['name' => 'Silver Rule', 'min_credit_score' => 550, 'max_credit_score' => 649]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.loan-offers.rules'))
        ->assertOk();

    expect($resp->json('data.rules'))->toHaveCount(2);
});

test('can update a rule', function () {
    $admin = offerAdmin();
    $plan = offerPlan();
    $rule = offerRule($plan);

    $this->actingAs($admin)
        ->putJson(route('api.v1.loan-offers.rules.update', $rule), ['validity_days' => 60])
        ->assertOk()
        ->assertJsonPath('data.rule.validity_days', 60);
});

test('can delete a rule', function () {
    $admin = offerAdmin();
    $plan = offerPlan();
    $rule = offerRule($plan);

    $this->actingAs($admin)
        ->deleteJson(route('api.v1.loan-offers.rules.destroy', $rule))
        ->assertOk();

    $this->assertDatabaseMissing('loan_offer_rules', ['id' => $rule->id]);
});

test('rule validation rejects invalid credit scores', function () {
    $admin = offerAdmin();
    $plan = offerPlan();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loan-offers.rules.store'), [
            'name' => 'Bad',
            'min_credit_score' => 900,   // > 850
            'max_credit_score' => 850,
            'loan_plan_id' => $plan->id,
            'min_offered_amount' => 1000,
            'max_offered_amount' => 5000,
        ])
        ->assertJsonValidationErrors(['min_credit_score']);
});

// ─── Offer Generation ─────────────────────────────────────────────────────────

test('service generates offer for borrower matching rule credit score', function () {
    $plan = offerPlan();
    $rule = offerRule($plan, ['min_credit_score' => 700, 'max_credit_score' => 850]);
    $borrower = offerBorrower(750);

    $offers = app(LoanOfferService::class)->generateForBorrower($borrower);

    expect($offers)->toHaveCount(1);
    expect($offers[0]->borrower_id)->toBe($borrower->id);
    expect($offers[0]->status)->toBe('pending');
});

test('no offer generated when borrower score is below rule minimum', function () {
    $plan = offerPlan();
    offerRule($plan, ['min_credit_score' => 700, 'max_credit_score' => 850]);
    $borrower = offerBorrower(620);  // below 700

    $offers = app(LoanOfferService::class)->generateForBorrower($borrower);

    expect($offers)->toHaveCount(0);
});

test('offered amount scales with credit score within rule range', function () {
    $plan = offerPlan();
    offerRule($plan, [
        'min_credit_score' => 600,
        'max_credit_score' => 800,
        'min_offered_amount' => 10000,
        'max_offered_amount' => 50000,
    ]);

    $lowBorrower = offerBorrower(600);
    $highBorrower = offerBorrower(800);

    $service = app(LoanOfferService::class);
    $lowOffers = $service->generateForBorrower($lowBorrower);
    $highOffers = $service->generateForBorrower($highBorrower);

    expect($highOffers[0]->offered_amount)->toBeGreaterThan($lowOffers[0]->offered_amount);
});

test('duplicate offer not created for same rule and borrower', function () {
    $plan = offerPlan();
    offerRule($plan, ['min_credit_score' => 600, 'max_credit_score' => 850]);
    $borrower = offerBorrower(700);

    $service = app(LoanOfferService::class);
    $service->generateForBorrower($borrower);
    $service->generateForBorrower($borrower);  // second call should not create

    expect(LoanOffer::where('borrower_id', $borrower->id)->count())->toBe(1);
});

// ─── Generate via API ─────────────────────────────────────────────────────────

test('can generate offer via API', function () {
    $admin = offerAdmin();
    $plan = offerPlan();
    offerRule($plan);
    $borrower = offerBorrower(720);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loan-offers.generate'), ['borrower_id' => $borrower->id])
        ->assertOk();

    expect($resp->json('data.generated'))->toBe(1);
});

// ─── Accept / Decline / Expire ────────────────────────────────────────────────

test('can accept a pending offer', function () {
    $admin = offerAdmin();
    $plan = offerPlan();
    $rule = offerRule($plan);
    $borrower = offerBorrower(720);

    $offer = LoanOffer::create([
        'loan_offer_rule_id' => $rule->id,
        'borrower_id' => $borrower->id,
        'loan_plan_id' => $plan->id,
        'offered_amount' => 20000,
        'interest_rate' => 24.0,
        'tenure' => 12,
        'credit_score_at_offer' => 720,
        'status' => 'pending',
        'expires_at' => now()->addDays(30),
    ]);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loan-offers.accept', $offer))
        ->assertOk();

    expect($resp->json('data.offer.status'))->toBe('accepted');
    expect($offer->fresh()->accepted_at)->not->toBeNull();
});

test('can decline a pending offer with reason', function () {
    $admin = offerAdmin();
    $plan = offerPlan();
    $rule = offerRule($plan);
    $borrower = offerBorrower(720);

    $offer = LoanOffer::create([
        'loan_offer_rule_id' => $rule->id,
        'borrower_id' => $borrower->id,
        'loan_plan_id' => $plan->id,
        'offered_amount' => 15000,
        'interest_rate' => 24.0,
        'tenure' => 12,
        'credit_score_at_offer' => 720,
        'status' => 'pending',
        'expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loan-offers.decline', $offer), ['reason' => 'Not needed'])
        ->assertOk()
        ->assertJsonPath('data.offer.status', 'declined');

    expect($offer->fresh()->decline_reason)->toBe('Not needed');
});

test('cannot accept an already-declined offer', function () {
    $admin = offerAdmin();
    $plan = offerPlan();
    $rule = offerRule($plan);
    $borrower = offerBorrower(720);

    $offer = LoanOffer::create([
        'loan_offer_rule_id' => $rule->id,
        'borrower_id' => $borrower->id,
        'loan_plan_id' => $plan->id,
        'offered_amount' => 10000,
        'interest_rate' => 24.0,
        'tenure' => 12,
        'credit_score_at_offer' => 720,
        'status' => 'declined',
        'expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loan-offers.accept', $offer))
        ->assertStatus(422);
});

test('expire stale offers command marks expired', function () {
    $plan = offerPlan();
    $rule = offerRule($plan);
    $borrower = offerBorrower(720);

    LoanOffer::create([
        'loan_offer_rule_id' => $rule->id,
        'borrower_id' => $borrower->id,
        'loan_plan_id' => $plan->id,
        'offered_amount' => 10000,
        'interest_rate' => 24.0,
        'tenure' => 12,
        'status' => 'pending',
        'expires_at' => now()->subDay(),  // already expired
    ]);

    $count = app(LoanOfferService::class)->expireStale();

    expect($count)->toBe(1);
    expect(LoanOffer::where('status', 'expired')->count())->toBe(1);
});

// ─── List / Show ──────────────────────────────────────────────────────────────

test('can list offers with filters', function () {
    $admin = offerAdmin();
    $plan = offerPlan();
    $rule = offerRule($plan);
    $borrower = offerBorrower(720);

    LoanOffer::create(['loan_offer_rule_id' => $rule->id, 'borrower_id' => $borrower->id, 'loan_plan_id' => $plan->id, 'offered_amount' => 10000, 'interest_rate' => 24, 'tenure' => 12, 'status' => 'pending', 'expires_at' => now()->addDays(30)]);
    LoanOffer::create(['loan_offer_rule_id' => $rule->id, 'borrower_id' => $borrower->id, 'loan_plan_id' => $plan->id, 'offered_amount' => 5000,  'interest_rate' => 24, 'tenure' => 12, 'status' => 'accepted', 'expires_at' => now()->addDays(30)]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.loan-offers.index', ['status' => 'pending']))
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

// ─── Auth ─────────────────────────────────────────────────────────────────────

test('unauthenticated cannot access loan offer endpoints', function () {
    $this->getJson(route('api.v1.loan-offers.index'))->assertStatus(401);
});
