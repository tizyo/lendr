<?php

use App\Enums\LoanStatus;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\Payment;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function portalBorrower(array $attrs = []): Borrower
{
    return Borrower::factory()->create(array_merge(['is_active' => true, 'kyc_verified' => true], $attrs));
}

function portalLoan(Borrower $borrower, array $attrs = []): Loan
{
    $type = LoanType::factory()->create();
    $plan = LoanPlan::factory()->create(['loan_type_id' => $type->id]);

    return Loan::factory()->create(array_merge([
        'borrower_id'  => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'status'       => LoanStatus::Active,
    ], $attrs));
}

// ─── Profile ──────────────────────────────────────────────────────────────────

test('authenticated borrower can view their profile', function () {
    $borrower = portalBorrower();

    $this->actingAs($borrower, 'sanctum')
        ->getJson(route('api.v1.borrower.profile'))
        ->assertOk()
        ->assertJsonPath('data.borrower_number', $borrower->borrower_number)
        ->assertJsonPath('data.phone', $borrower->phone);
})->group('borrower-portal');

test('unauthenticated request to profile is rejected', function () {
    $this->getJson(route('api.v1.borrower.profile'))
        ->assertUnauthorized();
})->group('borrower-portal');

test('borrower can update their profile details', function () {
    $borrower = portalBorrower();

    $this->actingAs($borrower, 'sanctum')
        ->putJson(route('api.v1.borrower.profile.update'), [
            'first_name' => 'Alice',
            'last_name'  => 'Smith',
            'city'       => 'Ndola',
        ])
        ->assertOk();

    $borrower->refresh();
    $this->assertSame('Alice', $borrower->first_name);
    $this->assertSame('Smith', $borrower->last_name);
    $this->assertSame('Ndola', $borrower->city);
})->group('borrower-portal');

test('profile update rejects invalid date_of_birth', function () {
    $borrower = portalBorrower();

    $this->actingAs($borrower, 'sanctum')
        ->putJson(route('api.v1.borrower.profile.update'), [
            'date_of_birth' => now()->addDay()->toDateString(), // future date
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date_of_birth']);
})->group('borrower-portal');

// ─── Borrower Loans ───────────────────────────────────────────────────────────

test('borrower can list their own loans', function () {
    $borrower = portalBorrower();
    portalLoan($borrower);
    portalLoan($borrower);

    // Another borrower's loan — should NOT appear
    $other = portalBorrower();
    portalLoan($other);

    $this->actingAs($borrower, 'sanctum')
        ->getJson(route('api.v1.borrower.loans'))
        ->assertOk()
        ->assertJsonCount(2, 'data');
})->group('borrower-portal');

test('borrower profile counts active loans', function () {
    $borrower = portalBorrower();
    portalLoan($borrower, ['status' => LoanStatus::Active]);
    portalLoan($borrower, ['status' => LoanStatus::Active]);
    portalLoan($borrower, ['status' => LoanStatus::Completed]);

    $this->actingAs($borrower, 'sanctum')
        ->getJson(route('api.v1.borrower.profile'))
        ->assertOk()
        ->assertJsonPath('data.loans_count', 3)
        ->assertJsonPath('data.active_loans_count', 2);
})->group('borrower-portal');

// ─── Loan Products ────────────────────────────────────────────────────────────

test('borrower can view available loan products', function () {
    $type = LoanType::factory()->create(['is_active' => true]);
    LoanPlan::factory()->create(['loan_type_id' => $type->id, 'is_active' => true]);

    $borrower = portalBorrower();

    $this->actingAs($borrower, 'sanctum')
        ->getJson(route('api.v1.borrower.loan-products'))
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'plans']]]);
})->group('borrower-portal');

test('inactive loan types are excluded from products', function () {
    LoanType::factory()->create(['is_active' => false]);
    $active = LoanType::factory()->create(['is_active' => true]);
    LoanPlan::factory()->create(['loan_type_id' => $active->id]);

    $borrower = portalBorrower();

    $response = $this->actingAs($borrower, 'sanctum')
        ->getJson(route('api.v1.borrower.loan-products'))
        ->assertOk();

    $types = $response->json('data');
    foreach ($types as $t) {
        $this->assertTrue($t['id'] !== null);
    }
    // All returned types should be active ones only
    $this->assertCount(1, $types);
})->group('borrower-portal');

// ─── Loan Application ─────────────────────────────────────────────────────────

test('borrower can apply for a loan', function () {
    $type    = LoanType::factory()->create(['is_active' => true]);
    $plan    = LoanPlan::factory()->create([
        'loan_type_id' => $type->id,
        'min_amount'   => 500,
        'max_amount'   => 50000,
        'min_tenure'   => 1,
        'max_tenure'   => 24,
        'is_active'    => true,
    ]);
    $borrower = portalBorrower();

    $this->actingAs($borrower, 'sanctum')
        ->postJson(route('api.v1.borrower.loans.apply'), [
            'loan_type_id'     => $type->id,
            'loan_plan_id'     => $plan->id,
            'principal_amount' => 5000,
            'tenure'           => 6,
            'loan_purpose'     => 'Business expansion',
        ])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['loan_number', 'status']]);

    $this->assertDatabaseHas('loans', [
        'borrower_id'  => $borrower->id,
        'loan_type_id' => $type->id,
    ]);
})->group('borrower-portal');

test('loan application fails when amount is below plan minimum', function () {
    $type = LoanType::factory()->create(['is_active' => true]);
    $plan = LoanPlan::factory()->create([
        'loan_type_id' => $type->id,
        'min_amount'   => 1000,
        'max_amount'   => 50000,
        'is_active'    => true,
    ]);
    $borrower = portalBorrower();

    $this->actingAs($borrower, 'sanctum')
        ->postJson(route('api.v1.borrower.loans.apply'), [
            'loan_type_id'     => $type->id,
            'loan_plan_id'     => $plan->id,
            'principal_amount' => 100, // below minimum
            'tenure'           => 6,
        ])
        ->assertUnprocessable();
})->group('borrower-portal');

test('loan application fails when inactive plan is selected', function () {
    $type = LoanType::factory()->create(['is_active' => true]);
    $plan = LoanPlan::factory()->create([
        'loan_type_id' => $type->id,
        'is_active'    => false,
    ]);
    $borrower = portalBorrower();

    $this->actingAs($borrower, 'sanctum')
        ->postJson(route('api.v1.borrower.loans.apply'), [
            'loan_type_id'     => $type->id,
            'loan_plan_id'     => $plan->id,
            'principal_amount' => 5000,
            'tenure'           => 6,
        ])
        ->assertUnprocessable();
})->group('borrower-portal');

test('inactive borrower cannot apply for a loan', function () {
    $type    = LoanType::factory()->create(['is_active' => true]);
    $plan    = LoanPlan::factory()->create(['loan_type_id' => $type->id, 'is_active' => true]);
    $borrower = portalBorrower(['is_active' => false]);

    $this->actingAs($borrower, 'sanctum')
        ->postJson(route('api.v1.borrower.loans.apply'), [
            'loan_type_id'     => $type->id,
            'loan_plan_id'     => $plan->id,
            'principal_amount' => 5000,
            'tenure'           => 6,
        ])
        ->assertForbidden();
})->group('borrower-portal');

// ─── Credit Score ─────────────────────────────────────────────────────────────

test('borrower can view their credit score', function () {
    $borrower = portalBorrower(['credit_score' => 720]);

    $this->actingAs($borrower, 'sanctum')
        ->getJson(route('api.v1.borrower.credit-score'))
        ->assertOk()
        ->assertJsonPath('data.score', 720);
})->group('borrower-portal');
