<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Guarantor;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function guarantorAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function guarantorLoan(): Loan
{
    $type     = LoanType::first() ?? LoanType::factory()->create();
    $plan     = LoanPlan::first() ?? LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create();

    return Loan::factory()->create([
        'borrower_id'  => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'status'       => LoanStatus::Active,
    ]);
}

function makeGuarantor(Loan $loan, array $attrs = []): Guarantor
{
    return $loan->guarantors()->create(array_merge([
        'name'           => 'John Guarantor',
        'phone'          => '0977'.rand(100000, 999999),
        'relationship'   => 'spouse',
        'monthly_income' => 3000.00,
        'status'         => 'pending',
    ], $attrs));
}

// ─── Tests ───────────────────────────────────────────────────────────────────

test('can list guarantors for a loan', function () {
    $admin = guarantorAdmin();
    $loan  = guarantorLoan();

    makeGuarantor($loan);
    makeGuarantor($loan, ['name' => 'Jane Guarantor']);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.loans.guarantors.index', $loan))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(2);
});

test('can add a guarantor to a loan', function () {
    $admin = guarantorAdmin();
    $loan  = guarantorLoan();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loans.guarantors.store', $loan), [
            'name'           => 'Mary Guarantor',
            'phone'          => '0977100001',
            'relationship'   => 'parent',
            'monthly_income' => 5000,
        ])
        ->assertCreated();

    expect($resp->json('data.name'))->toBe('Mary Guarantor')
        ->and($resp->json('data.status'))->toBe('pending');

    $this->assertDatabaseHas('guarantors', ['loan_id' => $loan->id, 'name' => 'Mary Guarantor']);
});

test('can show a single guarantor', function () {
    $admin     = guarantorAdmin();
    $loan      = guarantorLoan();
    $guarantor = makeGuarantor($loan);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.guarantors.show', $guarantor))
        ->assertOk();

    expect($resp->json('data.id'))->toBe($guarantor->id)
        ->and($resp->json('data.name'))->toBe($guarantor->name);
});

test('can update a guarantor', function () {
    $admin     = guarantorAdmin();
    $loan      = guarantorLoan();
    $guarantor = makeGuarantor($loan);

    $resp = $this->actingAs($admin)
        ->putJson(route('api.v1.guarantors.update', $guarantor), [
            'status' => 'approved',
            'notes'  => 'Verified in person.',
        ])
        ->assertOk();

    expect($resp->json('data.status'))->toBe('approved');
    $this->assertDatabaseHas('guarantors', ['id' => $guarantor->id, 'status' => 'approved']);
});

test('can delete a guarantor', function () {
    $admin     = guarantorAdmin();
    $loan      = guarantorLoan();
    $guarantor = makeGuarantor($loan);

    $this->actingAs($admin)
        ->deleteJson(route('api.v1.guarantors.destroy', $guarantor))
        ->assertOk();

    $this->assertSoftDeleted('guarantors', ['id' => $guarantor->id]);
});

test('store validates required name', function () {
    $admin = guarantorAdmin();
    $loan  = guarantorLoan();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.guarantors.store', $loan), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('update validates status enum', function () {
    $admin     = guarantorAdmin();
    $loan      = guarantorLoan();
    $guarantor = makeGuarantor($loan);

    $this->actingAs($admin)
        ->putJson(route('api.v1.guarantors.update', $guarantor), [
            'status' => 'unknown_status',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('unauthenticated cannot manage guarantors', function () {
    $loan = guarantorLoan();

    $this->getJson(route('api.v1.loans.guarantors.index', $loan))
        ->assertUnauthorized();
});
