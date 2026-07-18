<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\CollateralItem;
use App\Models\Tenant\Guarantor;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function gcUser(): User
{
    return User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'is_active' => true,
    ]);
}

function gcLoan(): Loan
{
    $type = LoanType::factory()->create();
    $plan = LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create();

    return Loan::factory()->create([
        'borrower_id' => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'status' => LoanStatus::Draft,
    ]);
}

// ─── Guarantor Tests ─────────────────────────────────────────────────────────

test('can list guarantors for a loan', function () {
    $user = gcUser();
    $loan = gcLoan();

    Guarantor::create([
        'loan_id' => $loan->id,
        'name' => 'Jane Mwila',
        'phone' => '0977000001',
    ]);

    $this->actingAs($user)
        ->getJson(route('api.v1.loans.guarantors.index', $loan->id))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Jane Mwila');
});

test('can add a guarantor to a loan', function () {
    $user = gcUser();
    $loan = gcLoan();

    $this->actingAs($user)
        ->postJson(route('api.v1.loans.guarantors.store', $loan->id), [
            'name' => 'John Banda',
            'phone' => '0966123456',
            'relationship' => 'Spouse',
            'employer' => 'ZRA',
            'monthly_income' => 12000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'John Banda')
        ->assertJsonPath('data.status', 'pending');

    expect(Guarantor::where('loan_id', $loan->id)->count())->toBe(1);
});

test('guarantor name is required', function () {
    $user = gcUser();
    $loan = gcLoan();

    $this->actingAs($user)
        ->postJson(route('api.v1.loans.guarantors.store', $loan->id), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('can update a guarantor status to approved', function () {
    $user = gcUser();
    $loan = gcLoan();
    $guarantor = Guarantor::create(['loan_id' => $loan->id, 'name' => 'Test Person']);

    $this->actingAs($user)
        ->putJson(route('api.v1.guarantors.update', $guarantor->id), [
            'status' => 'approved',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($guarantor->fresh()->status)->toBe('approved');
});

test('can delete a guarantor', function () {
    $user = gcUser();
    $loan = gcLoan();
    $guarantor = Guarantor::create(['loan_id' => $loan->id, 'name' => 'Delete Me']);

    $this->actingAs($user)
        ->deleteJson(route('api.v1.guarantors.destroy', $guarantor->id))
        ->assertOk();

    expect(Guarantor::find($guarantor->id))->toBeNull();
    expect(Guarantor::withTrashed()->find($guarantor->id))->not->toBeNull();
});

test('guarantor belongs to the correct loan', function () {
    $user = gcUser();
    $loan = gcLoan();

    $this->actingAs($user)
        ->postJson(route('api.v1.loans.guarantors.store', $loan->id), [
            'name' => 'Mary Phiri',
        ])
        ->assertCreated()
        ->assertJsonPath('data.loan_id', $loan->id);
});

// ─── Collateral Tests ─────────────────────────────────────────────────────────

test('can list collateral items for a loan', function () {
    $user = gcUser();
    $loan = gcLoan();

    CollateralItem::create([
        'loan_id' => $loan->id,
        'type' => 'vehicle',
        'description' => 'Toyota Hilux 2020',
    ]);

    $this->actingAs($user)
        ->getJson(route('api.v1.loans.collateral.index', $loan->id))
        ->assertOk()
        ->assertJsonPath('data.0.description', 'Toyota Hilux 2020');
});

test('can add a collateral item to a loan', function () {
    $user = gcUser();
    $loan = gcLoan();

    $this->actingAs($user)
        ->postJson(route('api.v1.loans.collateral.store', $loan->id), [
            'type' => 'property',
            'description' => 'House Plot 123, Lusaka',
            'estimated_value' => 500000,
            'location' => 'Lusaka, Zambia',
        ])
        ->assertCreated()
        ->assertJsonPath('data.description', 'House Plot 123, Lusaka')
        ->assertJsonPath('data.type', 'property')
        ->assertJsonPath('data.type_label', 'Property')
        ->assertJsonPath('data.status', 'pending');

    expect(CollateralItem::where('loan_id', $loan->id)->count())->toBe(1);
});

test('collateral type and description are required', function () {
    $user = gcUser();
    $loan = gcLoan();

    $this->actingAs($user)
        ->postJson(route('api.v1.loans.collateral.store', $loan->id), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'description']);
});

test('collateral type must be a valid enum value', function () {
    $user = gcUser();
    $loan = gcLoan();

    $this->actingAs($user)
        ->postJson(route('api.v1.loans.collateral.store', $loan->id), [
            'type' => 'crypto',
            'description' => 'Bitcoin wallet',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

test('can update a collateral item status to verified', function () {
    $user = gcUser();
    $loan = gcLoan();
    $item = CollateralItem::create([
        'loan_id' => $loan->id,
        'type' => 'land',
        'description' => 'Farm land 5 hectares',
    ]);

    $this->actingAs($user)
        ->putJson(route('api.v1.collateral.update', $item->id), [
            'status' => 'verified',
            'assessed_value' => 250000,
            'assessment_date' => now()->toDateString(),
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'verified');

    expect($item->fresh()->status)->toBe('verified');
    expect((float) $item->fresh()->assessed_value)->toBe(250000.0);
});

test('can delete a collateral item', function () {
    $user = gcUser();
    $loan = gcLoan();
    $item = CollateralItem::create([
        'loan_id' => $loan->id,
        'type' => 'equipment',
        'description' => 'Industrial Generator',
    ]);

    $this->actingAs($user)
        ->deleteJson(route('api.v1.collateral.destroy', $item->id))
        ->assertOk();

    expect(CollateralItem::find($item->id))->toBeNull();
    expect(CollateralItem::withTrashed()->find($item->id))->not->toBeNull();
});

test('a loan can have multiple guarantors and collateral items', function () {
    $user = gcUser();
    $loan = gcLoan();

    Guarantor::create(['loan_id' => $loan->id, 'name' => 'Guarantor One']);
    Guarantor::create(['loan_id' => $loan->id, 'name' => 'Guarantor Two']);
    CollateralItem::create(['loan_id' => $loan->id, 'type' => 'vehicle',  'description' => 'Vehicle A']);
    CollateralItem::create(['loan_id' => $loan->id, 'type' => 'property', 'description' => 'House B']);

    $gRes = $this->actingAs($user)
        ->getJson(route('api.v1.loans.guarantors.index', $loan->id))
        ->assertOk();

    $cRes = $this->actingAs($user)
        ->getJson(route('api.v1.loans.collateral.index', $loan->id))
        ->assertOk();

    expect($gRes->json('data'))->toHaveCount(2);
    expect($cRes->json('data'))->toHaveCount(2);
});

test('guarantors are soft deleted and excluded from index', function () {
    $user = gcUser();
    $loan = gcLoan();
    $guarantor = Guarantor::create(['loan_id' => $loan->id, 'name' => 'Soft Delete Test']);

    $guarantor->delete();

    $this->actingAs($user)
        ->getJson(route('api.v1.loans.guarantors.index', $loan->id))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
