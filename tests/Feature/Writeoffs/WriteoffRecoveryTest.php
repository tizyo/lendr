<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\LoanWriteoff;
use App\Models\Tenant\User;
use Spatie\Permission\Models\Permission;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function writeoffAdmin(): User
{
    $user = User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
    Permission::firstOrCreate(['name' => 'loans.write_off', 'guard_name' => 'web']);
    $user->givePermissionTo('loans.write_off');

    return $user;
}

function writtenOffLoan(): Loan
{
    $type = LoanType::factory()->create();
    $plan = LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create();
    $staff = User::factory()->create(['role' => UserRole::LoanOfficer, 'is_active' => true]);

    return Loan::factory()->create([
        'borrower_id' => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'created_by' => $staff->id,
        'status' => LoanStatus::Defaulted->value,
        'outstanding_balance' => 5000.00,
    ]);
}

// ─── Write-off tests ─────────────────────────────────────────────────────────

test('write off a defaulted loan creates a writeoff record', function () {
    $admin = writeoffAdmin();
    $loan = writtenOffLoan();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.write-off', $loan), ['reason' => 'Borrower is untraceable.'])
        ->assertOk();

    $this->assertDatabaseHas('loan_writeoffs', [
        'loan_id' => $loan->id,
        'written_off_by' => $admin->id,
        'written_off_amount' => 5000.00,
    ]);
})->group('writeoffs');

test('write off updates loan status to written_off', function () {
    $admin = writeoffAdmin();
    $loan = writtenOffLoan();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.write-off', $loan), ['reason' => 'Non-performing.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'written_off');
})->group('writeoffs');

test('write off a frozen loan', function () {
    $admin = writeoffAdmin();
    $loan = writtenOffLoan();
    $loan->update(['status' => LoanStatus::Frozen->value]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.write-off', $loan), ['reason' => 'Account frozen.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'written_off');
})->group('writeoffs');

test('write off requires a reason', function () {
    $admin = writeoffAdmin();
    $loan = writtenOffLoan();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.write-off', $loan), [])
        ->assertJsonValidationErrors(['reason']);
})->group('writeoffs');

test('cannot write off an active loan', function () {
    $admin = writeoffAdmin();
    $loan = writtenOffLoan();
    $loan->update(['status' => LoanStatus::Active->value]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.write-off', $loan), ['reason' => 'Test.'])
        ->assertStatus(422);
})->group('writeoffs');

// ─── Recovery tests ───────────────────────────────────────────────────────────

test('record a recovery on a written-off loan', function () {
    $admin = writeoffAdmin();
    $loan = writtenOffLoan();

    // Write off first
    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.write-off', $loan), ['reason' => 'Non-performing.']);

    // Record recovery
    $response = $this->actingAs($admin)
        ->postJson(route('api.v1.loans.writeoff.recovery', $loan), [
            'amount' => 1000,
            'method' => 'cash',
            'reference' => 'RCV-001',
        ])
        ->assertStatus(201);

    expect($response->json('data.recovery.amount'))->toEqual(1000);
    expect($response->json('data.total_recovered'))->toEqual(1000);
})->group('writeoffs');

test('recovery updates total_recovered on writeoff record', function () {
    $admin = writeoffAdmin();
    $loan = writtenOffLoan();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.write-off', $loan), ['reason' => 'Non-performing.']);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.writeoff.recovery', $loan), ['amount' => 1000, 'method' => 'cash']);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.writeoff.recovery', $loan), ['amount' => 500, 'method' => 'bank_transfer']);

    $writeoff = LoanWriteoff::where('loan_id', $loan->id)->first();
    expect((float) $writeoff->total_recovered)->toEqual(1500);
})->group('writeoffs');

test('recovery requires amount and method', function () {
    $admin = writeoffAdmin();
    $loan = writtenOffLoan();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.write-off', $loan), ['reason' => 'Test.']);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.writeoff.recovery', $loan), [])
        ->assertJsonValidationErrors(['amount', 'method']);
})->group('writeoffs');

test('recovery rejects invalid method', function () {
    $admin = writeoffAdmin();
    $loan = writtenOffLoan();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.write-off', $loan), ['reason' => 'Test.']);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.writeoff.recovery', $loan), ['amount' => 100, 'method' => 'barter'])
        ->assertJsonValidationErrors(['method']);
})->group('writeoffs');

test('show writeoff details for a written-off loan', function () {
    $admin = writeoffAdmin();
    $loan = writtenOffLoan();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.write-off', $loan), ['reason' => 'Test.']);

    $response = $this->actingAs($admin)
        ->getJson(route('api.v1.loans.writeoff.show', $loan))
        ->assertOk()
        ->assertJsonPath('data.loan_id', $loan->id);

    expect($response->json('data.written_off_amount'))->toEqual(5000);
})->group('writeoffs');

test('index lists all writeoffs', function () {
    $admin = writeoffAdmin();

    foreach (range(1, 3) as $_) {
        $loan = writtenOffLoan();
        $this->actingAs($admin)
            ->postJson(route('api.v1.loans.write-off', $loan), ['reason' => 'Test.']);
    }

    $this->actingAs($admin)
        ->getJson(route('api.v1.writeoffs.index'))
        ->assertOk()
        ->assertJsonPath('meta.total', 3);
})->group('writeoffs');

test('unauthenticated cannot write off', function () {
    $loan = writtenOffLoan();

    $this->postJson(route('api.v1.loans.write-off', $loan), ['reason' => 'Test.'])
        ->assertStatus(401);
})->group('writeoffs');
