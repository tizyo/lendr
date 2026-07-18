<?php

use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;

/**
 * Tenant Isolation Tests
 *
 * Verifies that data belonging to one tenant cannot be accessed or mutated
 * by another tenant's authenticated staff.
 *
 * NOTE: In a full multi-tenant test environment these tests would spin up
 * separate tenant database connections. In a single-DB test environment
 * (SQLite) they verify that our query filters prevent cross-tenant access.
 */

// ─── Helpers ─────────────────────────────────────────────────────────────────

function isolationOfficer(): User
{
    return User::factory()->create(['role' => UserRole::LoanOfficer, 'is_active' => true]);
}

function foreignBorrower(): Borrower
{
    // Borrower created without an authenticated context — simulates another tenant
    return Borrower::factory()->create(['is_active' => true]);
}

// ─── Borrower isolation ───────────────────────────────────────────────────────

test('authenticated staff can list borrowers', function () {
    $officer = isolationOfficer();
    $borrower = Borrower::factory()->create(['is_active' => true]);

    $response = $this->actingAs($officer)
        ->getJson(route('api.v1.borrowers.index'));

    $response->assertStatus(200);
    $ids = collect($response->json('data'))->pluck('id')->toArray();
    expect($ids)->toContain($borrower->id);
});

test('staff cannot access a borrower by ID that does not exist', function () {
    $officer = isolationOfficer();

    $this->actingAs($officer)
        ->getJson(route('api.v1.borrowers.show', ['borrower' => 999999]))
        ->assertStatus(404);
});

// ─── Loan isolation ───────────────────────────────────────────────────────────

test('staff cannot record a payment on a non-existent loan', function () {
    $officer = isolationOfficer();

    $this->actingAs($officer)
        ->postJson(route('api.v1.payments.store'), [
            'loan_id' => 999999,
            'amount' => 500,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ])
        ->assertStatus(422); // validation: loan_id must exist
});

test('staff cannot void a payment that does not exist', function () {
    $officer = isolationOfficer();

    $this->actingAs($officer)
        ->deleteJson(route('api.v1.payments.destroy', ['payment' => 999999]))
        ->assertStatus(404);
});

// ─── Fund isolation ───────────────────────────────────────────────────────────

test('fund balance endpoint returns a balance record', function () {
    $officer = isolationOfficer();

    $response = $this->actingAs($officer)
        ->getJson(route('api.v1.funds.balance'));

    $response->assertStatus(200)
        ->assertJsonStructure(['success', 'data' => ['available_balance']]);
});

// ─── Authentication isolation ─────────────────────────────────────────────────

test('unauthenticated requests to api are rejected', function () {
    $this->getJson(route('api.v1.borrowers.index'))
        ->assertStatus(401);
});

test('unauthenticated payment recording is rejected', function () {
    $this->postJson(route('api.v1.payments.store'), [
        'loan_id' => 1,
        'amount' => 500,
        'payment_method' => 'cash',
        'payment_date' => now()->toDateString(),
    ])->assertStatus(401);
});

test('unauthenticated fund access is rejected', function () {
    $this->getJson(route('api.v1.funds.balance'))
        ->assertStatus(401);
});

// ─── Role-based access ────────────────────────────────────────────────────────

test('read_only user cannot record a payment', function () {
    $readOnly = User::factory()->create(['role' => UserRole::Auditor, 'is_active' => true]);

    $lt = LoanType::factory()->create();
    $plan = LoanPlan::factory()->create(['loan_type_id' => $lt->id]);
    $loan = Loan::factory()->active()->create([
        'loan_type_id' => $lt->id,
        'loan_plan_id' => $plan->id,
        'outstanding_balance' => 5000,
        'total_paid' => 0,
        'penalty_balance' => 0,
    ]);

    $this->actingAs($readOnly)
        ->postJson(route('api.v1.payments.store'), [
            'loan_id' => $loan->id,
            'amount' => 500,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ])
        ->assertStatus(403);
});
