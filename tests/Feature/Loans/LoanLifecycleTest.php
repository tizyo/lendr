<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;
use Spatie\Permission\Models\Permission;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function loanUser(array $permissions = []): User
{
    $user = User::factory()->create([
        'role'      => UserRole::LoanOfficer,
        'is_active' => true,
    ]);

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    if ($permissions) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function apiAs(User $user): \Illuminate\Testing\TestResponse
{
    // Return a partial — caller chains the HTTP verb
    return app(\Illuminate\Foundation\Testing\TestCase::class);
}

function makePlan(): LoanPlan
{
    $type = LoanType::factory()->create();
    return LoanPlan::factory()->create(['loan_type_id' => $type->id]);
}

// ─── Tests ───────────────────────────────────────────────────────────────────

test('loan officer with permission can create a loan application', function () {
    $user     = loanUser(['loans.create']);
    $borrower = Borrower::factory()->create();
    $plan     = makePlan();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.loans.store'), [
            'borrower_id'      => $borrower->id,
            'loan_type_id'     => $plan->loan_type_id,
            'loan_plan_id'     => $plan->id,
            'principal_amount' => 5000,
            'tenure'           => 6,
            'application_date' => now()->toDateString(),
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'submitted');

    $this->assertDatabaseHas('loans', [
        'borrower_id'  => $borrower->id,
        'loan_plan_id' => $plan->id,
    ]);
});

test('loan number follows LN-YYYYMM-XXXXX format', function () {
    $user     = loanUser(['loans.create']);
    $borrower = Borrower::factory()->create();
    $plan     = makePlan();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.loans.store'), [
            'borrower_id'      => $borrower->id,
            'loan_type_id'     => $plan->loan_type_id,
            'loan_plan_id'     => $plan->id,
            'principal_amount' => 3000,
            'tenure'           => 3,
            'application_date' => now()->toDateString(),
        ]);

    $loanNumber = $response->json('data.loan_number');
    expect($loanNumber)->toMatch('/^LN-\d{6}-\d{5}$/');
});

test('unauthenticated request to loans API returns 401', function () {
    $this->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.loans.index'))
        ->assertUnauthorized();
});

test('loan creation without permission returns 403', function () {
    $user     = loanUser(); // no permissions
    $borrower = Borrower::factory()->create();
    $plan     = makePlan();

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.loans.store'), [
            'borrower_id'      => $borrower->id,
            'loan_type_id'     => $plan->loan_type_id,
            'loan_plan_id'     => $plan->id,
            'principal_amount' => 5000,
            'tenure'           => 6,
            'application_date' => now()->toDateString(),
        ])
        ->assertForbidden();
});

test('a submitted loan can be approved', function () {
    $user = loanUser(['loans.approve']);
    $loan = Loan::factory()->submitted()->create();

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.loans.approve', $loan))
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($loan->fresh()->status)->toBe(LoanStatus::Approved);
});

test('approval sets approved_by and approval_date', function () {
    $user = loanUser(['loans.approve']);
    $loan = Loan::factory()->submitted()->create();

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.loans.approve', $loan));

    $fresh = $loan->fresh();
    expect($fresh->approved_by)->toBe($user->id)
        ->and($fresh->approval_date->toDateString())->toBe(now()->toDateString());
});

test('a draft or denied loan cannot be approved', function () {
    $user = loanUser(['loans.approve']);
    $loan = Loan::factory()->denied()->create();

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.loans.approve', $loan))
        ->assertStatus(422);
});

test('an approved loan can be disbursed and generates a schedule', function () {
    $user = loanUser(['loans.disburse']);
    $plan = makePlan(); // flat, monthly, 6 months
    $loan = Loan::factory()->approved()->create([
        'loan_plan_id'     => $plan->id,
        'principal_amount' => 5000,
        'interest_amount'  => 1500,
        'tenure'           => 6,
    ]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.loans.disburse', $loan), [
            'disbursement_method' => 'cash',
            'disbursement_date'   => now()->toDateString(),
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $loan->refresh();
    expect($loan->status)->toBe(LoanStatus::Active);
    expect($loan->schedule()->count())->toBe(6);
});

test('a submitted loan can be denied', function () {
    $user = loanUser(['loans.approve']);
    $loan = Loan::factory()->submitted()->create();

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.loans.deny', $loan), [
            'reason' => 'Insufficient income documentation.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'denied');

    expect($loan->fresh()->status)->toBe(LoanStatus::Denied);
});

test('denial requires a reason', function () {
    $user = loanUser(['loans.approve']);
    $loan = Loan::factory()->submitted()->create();

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.loans.deny', $loan), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');
});

test('an active loan can be frozen with a reason', function () {
    $user = loanUser();
    $loan = Loan::factory()->active()->create();

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.loans.freeze', $loan), [
            'reason' => 'Disputed payment arrangement.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'frozen');

    expect($loan->fresh()->status)->toBe(LoanStatus::Frozen);
});

test('a frozen loan can be unfrozen back to active', function () {
    $user = loanUser();
    $loan = Loan::factory()->create(['status' => LoanStatus::Frozen->value]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.loans.unfreeze', $loan))
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
});

test('loan schedule is returned for an active loan', function () {
    $user = loanUser();
    $loan = Loan::factory()->active()->create();

    // Create 3 schedule rows manually
    $loan->schedule()->createMany([
        ['instalment_number' => 1, 'due_date' => now()->addMonth(), 'principal_due' => 833.33, 'interest_due' => 250, 'fee_due' => 0, 'total_due' => 1083.33, 'outstanding' => 1083.33],
        ['instalment_number' => 2, 'due_date' => now()->addMonths(2), 'principal_due' => 833.33, 'interest_due' => 250, 'fee_due' => 0, 'total_due' => 1083.33, 'outstanding' => 1083.33],
        ['instalment_number' => 3, 'due_date' => now()->addMonths(3), 'principal_due' => 833.34, 'interest_due' => 250, 'fee_due' => 0, 'total_due' => 1083.34, 'outstanding' => 1083.34],
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.loans.schedule', $loan));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
});

test('only draft or submitted loans can be deleted', function () {
    $user = loanUser();

    $denied = Loan::factory()->denied()->create();
    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->deleteJson(route('api.v1.loans.destroy', $denied))
        ->assertOk();

    $active = Loan::factory()->active()->create();
    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->deleteJson(route('api.v1.loans.destroy', $active))
        ->assertStatus(422);
});
