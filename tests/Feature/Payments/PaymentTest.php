<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\Payment;
use App\Models\Tenant\User;
use Spatie\Permission\Models\Permission;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function paymentUser(array $permissions = []): User
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

function activeLoan(array $overrides = []): Loan
{
    $type = LoanType::factory()->create();
    $plan = LoanPlan::factory()->create(['loan_type_id' => $type->id]);

    return Loan::factory()->active()->create(array_merge([
        'loan_plan_id'        => $plan->id,
        'loan_type_id'        => $type->id,
        'principal_amount'    => 5000.00,
        'interest_amount'     => 1500.00,
        'total_payable'       => 6600.00,  // includes 100 processing fee
        'outstanding_balance' => 6600.00,
        'total_paid'          => 0.00,
        'penalty_balance'     => 0.00,
    ], $overrides));
}

// ─── Tests ───────────────────────────────────────────────────────────────────

test('a payment can be recorded for an active loan', function () {
    $user = paymentUser();
    $loan = activeLoan();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.payments.store'), [
            'loan_id'        => $loan->id,
            'amount'         => 1000.00,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ]);

    $response->assertStatus(201);
    expect($response->json('data.amount'))->toEqual(1000);

    $this->assertDatabaseHas('payments', [
        'loan_id' => $loan->id,
        'amount'  => 1000.00,
    ]);
});

test('receipt number follows REC-YYYYMM-XXXXX format', function () {
    $user = paymentUser();
    $loan = activeLoan();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.payments.store'), [
            'loan_id'        => $loan->id,
            'amount'         => 500.00,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ]);

    $receiptNumber = $response->json('data.receipt_number');
    expect($receiptNumber)->toMatch('/^REC-\d{6}-\d{5}$/');
});

test('payment reduces outstanding balance', function () {
    $user = paymentUser();
    $loan = activeLoan(['outstanding_balance' => 6600.00]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.payments.store'), [
            'loan_id'        => $loan->id,
            'amount'         => 1100.00,
            'payment_method' => 'bank_transfer',
            'payment_date'   => now()->toDateString(),
        ]);

    $updated = $loan->fresh();
    expect((float) $updated->total_paid)->toBe(1100.0)
        ->and((float) $updated->outstanding_balance)->toBeLessThan(6600.0);
});

test('full payment marks loan as completed', function () {
    $user = paymentUser();
    $loan = activeLoan([
        'principal_amount'    => 1000.00,
        'interest_amount'     => 0.00,
        'total_payable'       => 1000.00,
        'outstanding_balance' => 1000.00,
        'penalty_balance'     => 0.00,
    ]);

    // No schedule rows — so unpaid interest is 0; full principal covers everything
    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.payments.store'), [
            'loan_id'        => $loan->id,
            'amount'         => 1000.00,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ]);

    expect($loan->fresh()->status)->toBe(LoanStatus::Completed);
});

test('payment cannot be recorded for a denied loan', function () {
    $user = paymentUser();
    $loan = Loan::factory()->denied()->create();

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.payments.store'), [
            'loan_id'        => $loan->id,
            'amount'         => 500.00,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ])
        ->assertStatus(422);
});

test('payment amount must be positive', function () {
    $user = paymentUser();
    $loan = activeLoan();

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.payments.store'), [
            'loan_id'        => $loan->id,
            'amount'         => 0,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('amount');
});

test('receipt endpoint returns printable data', function () {
    $user = paymentUser();
    $loan = activeLoan();

    $payment = Payment::create([
        'receipt_number'      => 'REC-'.now()->format('Ym').'-00001',
        'loan_id'             => $loan->id,
        'recorded_by'         => $user->id,
        'amount'              => 550.00,
        'principal_allocated' => 550.00,
        'interest_allocated'  => 0.00,
        'penalty_allocated'   => 0.00,
        'fee_allocated'       => 0.00,
        'payment_method'      => 'cash',
        'payment_date'        => now()->toDateString(),
        'source'              => 'manual',
        'is_overdue_payment'  => false,
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.payments.receipt', $payment));

    $response->assertOk()
        ->assertJsonStructure(['data' => [
            'receipt_number', 'payment_date', 'amount',
            'payment_method', 'loan_number', 'borrower_name',
            'outstanding_after',
        ]]);
});

test('a payment can be reversed by a user with payments.delete permission', function () {
    $user = paymentUser(['payments.delete']);
    $loan = activeLoan(['outstanding_balance' => 5000.00, 'total_paid' => 1600.00]);

    $payment = Payment::create([
        'receipt_number'      => 'REC-'.now()->format('Ym').'-00002',
        'loan_id'             => $loan->id,
        'recorded_by'         => $user->id,
        'amount'              => 1000.00,
        'principal_allocated' => 800.00,
        'interest_allocated'  => 200.00,
        'penalty_allocated'   => 0.00,
        'fee_allocated'       => 0.00,
        'payment_method'      => 'cash',
        'payment_date'        => now()->toDateString(),
        'source'              => 'manual',
        'is_overdue_payment'  => false,
    ]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->deleteJson(route('api.v1.payments.destroy', $payment))
        ->assertOk();

    $this->assertSoftDeleted('payments', ['id' => $payment->id]);

    $updated = $loan->fresh();
    expect((float) $updated->outstanding_balance)->toBe(6000.0)
        ->and((float) $updated->total_paid)->toBe(600.0);
});

test('payment reversal requires payments.delete permission', function () {
    $user = paymentUser(); // no permissions
    $loan = activeLoan();

    $payment = Payment::create([
        'receipt_number'      => 'REC-'.now()->format('Ym').'-00003',
        'loan_id'             => $loan->id,
        'recorded_by'         => $user->id,
        'amount'              => 500.00,
        'principal_allocated' => 500.00,
        'interest_allocated'  => 0.00,
        'penalty_allocated'   => 0.00,
        'fee_allocated'       => 0.00,
        'payment_method'      => 'cash',
        'payment_date'        => now()->toDateString(),
        'source'              => 'manual',
        'is_overdue_payment'  => false,
    ]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->deleteJson(route('api.v1.payments.destroy', $payment))
        ->assertForbidden();
});

test('payments for a specific loan can be listed', function () {
    $user = paymentUser();
    $loan = activeLoan();

    Payment::factory()->count(3)->create(['loan_id' => $loan->id]);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.loans.payments', $loan));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
});
