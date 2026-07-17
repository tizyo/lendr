<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\CollateralItem;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function collateralAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function collateralLoan(): Loan
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

function makeCollateral(Loan $loan, array $attrs = []): CollateralItem
{
    return $loan->collateralItems()->create(array_merge([
        'type'            => 'property',
        'description'     => 'Residential house on Stand 45',
        'estimated_value' => 80000.00,
        'location'        => 'Lusaka, Woodlands',
    ], $attrs));
}

// ─── Tests ───────────────────────────────────────────────────────────────────

test('can list collateral items for a loan', function () {
    $admin = collateralAdmin();
    $loan  = collateralLoan();

    makeCollateral($loan);
    makeCollateral($loan, ['type' => 'vehicle', 'description' => 'Toyota Hilux 2020']);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.loans.collateral.index', $loan))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(2);
});

test('can add a collateral item to a loan', function () {
    $admin = collateralAdmin();
    $loan  = collateralLoan();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loans.collateral.store', $loan), [
            'type'            => 'vehicle',
            'description'     => 'Land Rover Defender 2021',
            'estimated_value' => 120000,
        ])
        ->assertCreated();

    expect($resp->json('data.type'))->toBe('vehicle')
        ->and($resp->json('data.status'))->toBe('pending')
        ->and($resp->json('data.type_label'))->toBe('Vehicle');

    $this->assertDatabaseHas('collateral_items', [
        'loan_id'     => $loan->id,
        'description' => 'Land Rover Defender 2021',
    ]);
});

test('can show a single collateral item', function () {
    $admin      = collateralAdmin();
    $loan       = collateralLoan();
    $collateral = makeCollateral($loan);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.collateral.show', $collateral))
        ->assertOk();

    expect($resp->json('data.id'))->toBe($collateral->id)
        ->and($resp->json('data.description'))->toBe($collateral->description);
});

test('can update a collateral item status to verified', function () {
    $admin      = collateralAdmin();
    $loan       = collateralLoan();
    $collateral = makeCollateral($loan);

    $resp = $this->actingAs($admin)
        ->putJson(route('api.v1.collateral.update', $collateral), [
            'status'          => 'verified',
            'assessed_value'  => 75000,
            'assessment_date' => '2026-03-15',
        ])
        ->assertOk();

    expect($resp->json('data.status'))->toBe('verified');
    $this->assertDatabaseHas('collateral_items', [
        'id'     => $collateral->id,
        'status' => 'verified',
    ]);
});

test('can release a collateral item', function () {
    $admin      = collateralAdmin();
    $loan       = collateralLoan();
    $collateral = makeCollateral($loan, ['status' => 'verified']);

    $this->actingAs($admin)
        ->putJson(route('api.v1.collateral.update', $collateral), ['status' => 'released'])
        ->assertOk();

    $this->assertDatabaseHas('collateral_items', ['id' => $collateral->id, 'status' => 'released']);
});

test('can delete a collateral item', function () {
    $admin      = collateralAdmin();
    $loan       = collateralLoan();
    $collateral = makeCollateral($loan);

    $this->actingAs($admin)
        ->deleteJson(route('api.v1.collateral.destroy', $collateral))
        ->assertOk();

    $this->assertSoftDeleted('collateral_items', ['id' => $collateral->id]);
});

test('store validates required type and description', function () {
    $admin = collateralAdmin();
    $loan  = collateralLoan();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.collateral.store', $loan), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'description']);
});

test('store rejects invalid collateral type', function () {
    $admin = collateralAdmin();
    $loan  = collateralLoan();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.collateral.store', $loan), [
            'type'        => 'cryptocurrency',
            'description' => 'Bitcoin',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

test('unauthenticated cannot manage collateral', function () {
    $loan = collateralLoan();

    $this->getJson(route('api.v1.loans.collateral.index', $loan))
        ->assertUnauthorized();
});
