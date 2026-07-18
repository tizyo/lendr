<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\InsuranceClaim;
use App\Models\Tenant\InsuranceProduct;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanInsurance;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function insuranceAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function makeInsuranceProduct(array $attrs = []): InsuranceProduct
{
    return InsuranceProduct::create(array_merge([
        'name' => 'Credit Life Insurance',
        'code' => 'CLI-'.rand(1000, 9999),
        'premium_type' => 'percentage',
        'premium_rate' => 1.5,
        'coverage_type' => 'credit_life',
        'is_active' => true,
    ], $attrs));
}

function insuranceLoan(): Loan
{
    $type = LoanType::first() ?? LoanType::factory()->create();
    $plan = LoanPlan::first() ?? LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create();

    return Loan::factory()->create([
        'borrower_id' => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'status' => LoanStatus::Active,
        'principal_amount' => 10000,
    ]);
}

function makeLoanInsurance(Loan $loan, InsuranceProduct $product, User $admin): LoanInsurance
{
    return $loan->insurances()->create([
        'insurance_product_id' => $product->id,
        'recorded_by' => $admin->id,
        'policy_number' => LoanInsurance::generatePolicyNumber(),
        'sum_insured' => $loan->principal_amount,
        'premium_amount' => $product->calculatePremium((float) $loan->principal_amount),
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);
}

// ─── Insurance Product Tests ──────────────────────────────────────────────────

test('can list insurance products', function () {
    $admin = insuranceAdmin();
    makeInsuranceProduct();
    makeInsuranceProduct(['name' => 'Disability Cover', 'coverage_type' => 'disability']);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.insurance.products.index'))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(2);
});

test('can create an insurance product', function () {
    $admin = insuranceAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.insurance.products.store'), [
            'name' => 'Property Cover',
            'code' => 'PROP-01',
            'premium_type' => 'flat',
            'premium_rate' => 200,
            'coverage_type' => 'property',
        ])
        ->assertCreated();

    expect($resp->json('data.product.name'))->toBe('Property Cover')
        ->and($resp->json('data.product.code'))->toBe('PROP-01');

    $this->assertDatabaseHas('insurance_products', ['code' => 'PROP-01']);
});

test('product code must be unique', function () {
    $admin = insuranceAdmin();
    makeInsuranceProduct(['code' => 'DUPE-01']);

    $this->actingAs($admin)
        ->postJson(route('api.v1.insurance.products.store'), [
            'name' => 'Another',
            'code' => 'DUPE-01',
            'premium_type' => 'flat',
            'premium_rate' => 100,
            'coverage_type' => 'credit_life',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('can update an insurance product', function () {
    $admin = insuranceAdmin();
    $product = makeInsuranceProduct();

    $resp = $this->actingAs($admin)
        ->putJson(route('api.v1.insurance.products.update', $product), [
            'is_active' => false,
            'premium_rate' => 2.0,
        ])
        ->assertOk();

    expect($resp->json('data.product.is_active'))->toBeFalse()
        ->and((float) $resp->json('data.product.premium_rate'))->toBe(2.0);
});

test('can delete an insurance product', function () {
    $admin = insuranceAdmin();
    $product = makeInsuranceProduct();

    $this->actingAs($admin)
        ->deleteJson(route('api.v1.insurance.products.destroy', $product))
        ->assertOk();

    $this->assertSoftDeleted('insurance_products', ['id' => $product->id]);
});

// ─── Loan Policy Tests ────────────────────────────────────────────────────────

test('can attach insurance to a loan', function () {
    $admin = insuranceAdmin();
    $loan = insuranceLoan();
    $product = makeInsuranceProduct(['premium_rate' => 1.5]);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loans.insurance.attach', $loan), [
            'insurance_product_id' => $product->id,
            'sum_insured' => 10000,
            'start_date' => now()->toDateString(),
        ])
        ->assertCreated();

    expect($resp->json('data.policy.status'))->toBe('active')
        ->and((float) $resp->json('data.policy.premium_amount'))->toBe(150.0);  // 1.5% of 10000

    $this->assertDatabaseHas('loan_insurances', ['loan_id' => $loan->id]);
});

test('premium calculated correctly for percentage type', function () {
    $admin = insuranceAdmin();
    $loan = insuranceLoan();
    $product = makeInsuranceProduct(['premium_type' => 'percentage', 'premium_rate' => 2.0]);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loans.insurance.attach', $loan), [
            'insurance_product_id' => $product->id,
            'sum_insured' => 10000,
            'start_date' => now()->toDateString(),
        ])
        ->assertCreated();

    // 2% of 10000 = 200
    expect((float) $resp->json('data.policy.premium_amount'))->toBe(200.0);
});

test('can list loan policies', function () {
    $admin = insuranceAdmin();
    $loan = insuranceLoan();
    $product = makeInsuranceProduct();

    makeLoanInsurance($loan, $product, $admin);
    makeLoanInsurance($loan, $product, $admin);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.loans.insurance.index', $loan))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(2);
});

test('can update policy status to lapsed', function () {
    $admin = insuranceAdmin();
    $loan = insuranceLoan();
    $product = makeInsuranceProduct();
    $policy = makeLoanInsurance($loan, $product, $admin);

    $resp = $this->actingAs($admin)
        ->putJson(route('api.v1.insurance.policies.update', $policy), [
            'status' => 'lapsed',
        ])
        ->assertOk();

    expect($resp->json('data.policy.status'))->toBe('lapsed');
});

// ─── Claim Tests ──────────────────────────────────────────────────────────────

test('can file an insurance claim', function () {
    $admin = insuranceAdmin();
    $loan = insuranceLoan();
    $product = makeInsuranceProduct();
    $policy = makeLoanInsurance($loan, $product, $admin);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.insurance.policies.claims.store', $policy), [
            'claim_type' => 'death',
            'claim_amount' => 10000,
            'incident_date' => '2026-03-01',
            'description' => 'Borrower deceased.',
        ])
        ->assertCreated();

    expect($resp->json('data.claim.status'))->toBe('pending')
        ->and($resp->json('data.claim.claim_type'))->toBe('death');

    $this->assertDatabaseHas('insurance_claims', [
        'loan_insurance_id' => $policy->id,
        'claim_type' => 'death',
    ]);
});

test('can approve a claim', function () {
    $admin = insuranceAdmin();
    $loan = insuranceLoan();
    $product = makeInsuranceProduct();
    $policy = makeLoanInsurance($loan, $product, $admin);

    $claim = InsuranceClaim::create([
        'loan_insurance_id' => $policy->id,
        'recorded_by' => $admin->id,
        'claim_number' => InsuranceClaim::generateClaimNumber(),
        'claim_type' => 'disability',
        'claim_amount' => 8000,
        'incident_date' => '2026-03-10',
        'status' => 'pending',
    ]);

    $resp = $this->actingAs($admin)
        ->putJson(route('api.v1.insurance.claims.review', $claim), [
            'status' => 'approved',
            'approved_amount' => 7500,
        ])
        ->assertOk();

    expect($resp->json('data.claim.status'))->toBe('approved')
        ->and((float) $resp->json('data.claim.approved_amount'))->toBe(7500.0);
});

test('can list claims for a policy', function () {
    $admin = insuranceAdmin();
    $loan = insuranceLoan();
    $product = makeInsuranceProduct();
    $policy = makeLoanInsurance($loan, $product, $admin);

    InsuranceClaim::create([
        'loan_insurance_id' => $policy->id,
        'recorded_by' => $admin->id,
        'claim_number' => InsuranceClaim::generateClaimNumber(),
        'claim_type' => 'other',
        'claim_amount' => 5000,
        'incident_date' => now()->toDateString(),
        'status' => 'pending',
    ]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.insurance.policies.claims', $policy))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(1);
});

test('unauthenticated cannot access insurance endpoints', function () {
    $this->getJson(route('api.v1.insurance.products.index'))
        ->assertUnauthorized();
});
