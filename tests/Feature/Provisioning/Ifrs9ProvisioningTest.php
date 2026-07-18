<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\ProvisionRate;
use App\Models\Tenant\User;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function provisionAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function seedProvisionRates(): void
{
    ProvisionRate::seedDefaults();
}

function provisionLoan(int $dpdDays = 0): Loan
{
    $type = LoanType::first() ?? LoanType::factory()->create();
    $plan = LoanPlan::first() ?? LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create();

    $loan = Loan::factory()->create([
        'borrower_id' => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'status' => LoanStatus::Active,
        'principal_amount' => 10000,
        'outstanding_balance' => 10000,
    ]);

    // Create an overdue installment if dpdDays > 0
    if ($dpdDays > 0) {
        LoanSchedule::create([
            'loan_id' => $loan->id,
            'instalment_number' => 1,
            'due_date' => now()->subDays($dpdDays)->toDateString(),
            'principal_due' => 1000,
            'interest_due' => 100,
            'total_due' => 1100,
            'outstanding' => 1100,
            'is_paid' => false,
        ]);
    }

    return $loan;
}

// ─── Provision Rate Tests ─────────────────────────────────────────────────────

test('can seed default IFRS9 rates', function () {
    $admin = provisionAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.provisioning.rates.seed'))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(3);
    $this->assertDatabaseHas('provision_rates', ['stage' => 1]);
    $this->assertDatabaseHas('provision_rates', ['stage' => 2]);
    $this->assertDatabaseHas('provision_rates', ['stage' => 3]);
});

test('can list provision rates', function () {
    $admin = provisionAdmin();
    seedProvisionRates();

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.provisioning.rates.index'))
        ->assertOk();

    expect(count($resp->json('data')))->toBe(3);
});

test('can create a custom provision rate', function () {
    $admin = provisionAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.provisioning.rates.store'), [
            'stage_label' => 'Stage 2 — Special',
            'stage' => 2,
            'dpd_from' => 45,
            'dpd_to' => 60,
            'provision_rate' => 15.00,
        ])
        ->assertCreated();

    expect($resp->json('data.rate.stage'))->toBe(2)
        ->and((float) $resp->json('data.rate.provision_rate'))->toBe(15.0);
});

test('can update a provision rate', function () {
    $admin = provisionAdmin();
    seedProvisionRates();

    $rate = ProvisionRate::where('stage', 1)->first();

    $resp = $this->actingAs($admin)
        ->putJson(route('api.v1.provisioning.rates.update', $rate), [
            'provision_rate' => 1.5,
        ])
        ->assertOk();

    expect((float) $resp->json('data.rate.provision_rate'))->toBe(1.5);
});

// ─── Loan Provision Calculation ───────────────────────────────────────────────

test('provision for performing loan uses stage 1 rate', function () {
    $admin = provisionAdmin();
    seedProvisionRates();

    $loan = provisionLoan(0); // no overdue installments

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loans.provision', $loan))
        ->assertCreated();

    expect($resp->json('data.provision.stage'))->toBe(1)
        ->and($resp->json('data.provision.days_past_due'))->toBe(0);
});

test('provision for 45-day overdue loan uses stage 2 rate', function () {
    $admin = provisionAdmin();
    seedProvisionRates();

    $loan = provisionLoan(45); // 45 DPD → stage 2 (30-89)

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loans.provision', $loan))
        ->assertCreated();

    expect($resp->json('data.provision.stage'))->toBe(2)
        ->and($resp->json('data.provision.days_past_due'))->toBe(45);
});

test('provision for 120-day overdue loan uses stage 3 rate', function () {
    $admin = provisionAdmin();
    seedProvisionRates();

    $loan = provisionLoan(120); // 120 DPD → stage 3 (90+)

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loans.provision', $loan))
        ->assertCreated();

    expect($resp->json('data.provision.stage'))->toBe(3)
        ->and($resp->json('data.provision.days_past_due'))->toBe(120);

    // 50% of 10000 = 5000
    expect((float) $resp->json('data.provision.provision_amount'))->toBe(5000.0);
});

test('provision history is stored per loan', function () {
    $admin = provisionAdmin();
    seedProvisionRates();

    $loan = provisionLoan(0);

    $this->actingAs($admin)->postJson(route('api.v1.loans.provision', $loan));
    $this->actingAs($admin)->postJson(route('api.v1.loans.provision', $loan));

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.loans.provisions', $loan))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(2);
});

// ─── Portfolio Run ────────────────────────────────────────────────────────────

test('portfolio run calculates across all active loans', function () {
    $admin = provisionAdmin();
    seedProvisionRates();

    provisionLoan(0);   // stage 1
    provisionLoan(45);  // stage 2
    provisionLoan(120); // stage 3

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.provisioning.run'))
        ->assertOk();

    expect($resp->json('data.loans_assessed'))->toBeGreaterThanOrEqual(3)
        ->and($resp->json('data.total_provision'))->toBeGreaterThan(0);
});

test('portfolio summary aggregates latest provisions per loan', function () {
    $admin = provisionAdmin();
    seedProvisionRates();

    $loan = provisionLoan(120);
    $this->actingAs($admin)->postJson(route('api.v1.loans.provision', $loan));

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.provisioning.summary'))
        ->assertOk();

    expect($resp->json('data.total_provision'))->toBeGreaterThan(0);
});

test('unauthenticated cannot access provisioning endpoints', function () {
    $this->postJson(route('api.v1.provisioning.run'))
        ->assertUnauthorized();
});
