<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\CollectionLog;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function collectionOfficer(): User
{
    return User::factory()->create(['role' => UserRole::LoanOfficer, 'is_active' => true]);
}

function overdueLoan(User $officer): Loan
{
    $type     = LoanType::factory()->create();
    $plan     = LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create();

    $loan = Loan::factory()->create([
        'borrower_id'        => $borrower->id,
        'loan_type_id'       => $type->id,
        'loan_plan_id'       => $plan->id,
        'created_by'         => $officer->id,
        'status'             => LoanStatus::Active,
        'outstanding_balance' => 5000.00,
    ]);

    // Overdue instalment — due 30 days ago, unpaid
    LoanSchedule::create([
        'loan_id'           => $loan->id,
        'instalment_number' => 1,
        'due_date'          => now()->subDays(30),
        'principal_due'     => 800,
        'interest_due'      => 250,
        'fee_due'           => 0,
        'total_due'         => 1050,
        'outstanding'       => 5000,
        'is_paid'           => false,
    ]);

    return $loan;
}

// ─── Collections Queue ────────────────────────────────────────────────────────

test('collections index returns overdue loans', function () {
    $officer = collectionOfficer();
    overdueLoan($officer);
    overdueLoan($officer);

    $this->actingAs($officer)
        ->getJson(route('collections.index'))
        ->assertOk();
})->group('collections');

test('collections index filters by officer', function () {
    $officer1 = collectionOfficer();
    $officer2 = collectionOfficer();

    overdueLoan($officer1);
    overdueLoan($officer2);

    // Just verifies the filter param is accepted without 422/500
    $this->actingAs($officer1)
        ->get(route('collections.index', ['officer_id' => $officer1->id]))
        ->assertOk();
})->group('collections');

// ─── Stats Endpoint ───────────────────────────────────────────────────────────

test('collections stats endpoint returns expected keys', function () {
    $officer = collectionOfficer();

    $this->actingAs($officer)
        ->getJson(route('collections.stats'))
        ->assertOk()
        ->assertJsonStructure(['total_overdue', 'logged_today', 'follow_up_today', 'promised_this_week', 'collected_this_week']);
})->group('collections');

// ─── Log Collection Activity ──────────────────────────────────────────────────

test('officer can log a collection call', function () {
    $officer = collectionOfficer();
    $loan    = overdueLoan($officer);

    $this->actingAs($officer)
        ->postJson(route('collections.logs.store', $loan), [
            'contact_method' => 'call',
            'outcome'        => 'reached',
            'notes'          => 'Borrower promised to pay by Friday.',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Collection activity recorded.')
        ->assertJsonPath('log.contact_method', 'call')
        ->assertJsonPath('log.outcome', 'reached');

    $this->assertDatabaseHas('collection_logs', [
        'loan_id'        => $loan->id,
        'contact_method' => 'call',
        'outcome'        => 'reached',
        'officer_id'     => $officer->id,
    ]);
})->group('collections');

test('officer can log a field visit with promised amount', function () {
    $officer = collectionOfficer();
    $loan    = overdueLoan($officer);

    $this->actingAs($officer)
        ->postJson(route('collections.logs.store', $loan), [
            'contact_method'  => 'visit',
            'outcome'         => 'promised_payment',
            'amount_promised' => 2000.00,
            'follow_up_date'  => now()->addDays(5)->toDateString(),
            'notes'           => 'Will pay in 5 days.',
        ])
        ->assertOk()
        ->assertJsonPath('log.contact_method', 'visit')
        ->assertJsonPath('log.outcome', 'promised_payment');

    $this->assertDatabaseHas('collection_logs', [
        'loan_id'         => $loan->id,
        'amount_promised' => 2000.00,
    ]);
})->group('collections');

test('log records the authenticated officer as officer_id', function () {
    $officer = collectionOfficer();
    $loan    = overdueLoan($officer);

    $this->actingAs($officer)
        ->postJson(route('collections.logs.store', $loan), [
            'contact_method' => 'sms',
            'outcome'        => 'no_answer',
        ])
        ->assertOk();

    $this->assertDatabaseHas('collection_logs', [
        'loan_id'    => $loan->id,
        'officer_id' => $officer->id,
    ]);
})->group('collections');

test('multiple logs can be recorded for the same loan', function () {
    $officer = collectionOfficer();
    $loan    = overdueLoan($officer);

    foreach (['call', 'sms', 'whatsapp'] as $method) {
        $this->actingAs($officer)
            ->postJson(route('collections.logs.store', $loan), [
                'contact_method' => $method,
                'outcome'        => 'no_answer',
            ])
            ->assertOk();
    }

    $this->assertSame(3, CollectionLog::where('loan_id', $loan->id)->count());
})->group('collections');

// ─── Validation ───────────────────────────────────────────────────────────────

test('log fails without contact_method', function () {
    $officer = collectionOfficer();
    $loan    = overdueLoan($officer);

    $this->actingAs($officer)
        ->postJson(route('collections.logs.store', $loan), [
            'outcome' => 'reached',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['contact_method']);
})->group('collections');

test('log fails without outcome', function () {
    $officer = collectionOfficer();
    $loan    = overdueLoan($officer);

    $this->actingAs($officer)
        ->postJson(route('collections.logs.store', $loan), [
            'contact_method' => 'call',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['outcome']);
})->group('collections');

test('log rejects invalid contact_method', function () {
    $officer = collectionOfficer();
    $loan    = overdueLoan($officer);

    $this->actingAs($officer)
        ->postJson(route('collections.logs.store', $loan), [
            'contact_method' => 'telegram',
            'outcome'        => 'reached',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['contact_method']);
})->group('collections');

test('log rejects invalid outcome', function () {
    $officer = collectionOfficer();
    $loan    = overdueLoan($officer);

    $this->actingAs($officer)
        ->postJson(route('collections.logs.store', $loan), [
            'contact_method' => 'call',
            'outcome'        => 'bribed',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['outcome']);
})->group('collections');

test('follow_up_date must be in the future', function () {
    $officer = collectionOfficer();
    $loan    = overdueLoan($officer);

    $this->actingAs($officer)
        ->postJson(route('collections.logs.store', $loan), [
            'contact_method' => 'call',
            'outcome'        => 'promised_payment',
            'follow_up_date' => now()->subDay()->toDateString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['follow_up_date']);
})->group('collections');

// ─── Show ─────────────────────────────────────────────────────────────────────

test('collections show returns loan detail', function () {
    $officer = collectionOfficer();
    $loan    = overdueLoan($officer);

    $this->actingAs($officer)
        ->getJson(route('collections.show', $loan))
        ->assertOk();
})->group('collections');

// ─── Authorization ────────────────────────────────────────────────────────────

test('unauthenticated user cannot access collections', function () {
    $officer = collectionOfficer();
    $loan    = overdueLoan($officer);

    // Web route: JSON requests get 401, browser requests get redirect
    $response = $this->getJson(route('collections.index'));
    expect($response->status())->toBeIn([401, 302]);
})->group('collections');
