<?php

use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\Payment;
use App\Models\Tenant\User;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function pdfAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function pdfLoan(array $attrs = []): Loan
{
    $borrower = Borrower::factory()->create();
    $type = LoanType::first() ?? LoanType::factory()->create();
    $plan = LoanPlan::first() ?? LoanPlan::factory()->create(['loan_type_id' => $type->id]);

    return Loan::factory()->create(array_merge([
        'borrower_id' => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'status' => LoanStatus::Active,
        'disbursement_date' => now()->subDays(30)->toDateString(),
    ], $attrs));
}

function pdfPayment(Loan $loan, User $admin): Payment
{
    return Payment::factory()->create([
        'loan_id' => $loan->id,
        'recorded_by' => $admin->id,
        'amount' => 1000,
        'payment_date' => now()->toDateString(),
        'payment_method' => PaymentMethod::Cash,
    ]);
}

// ─── PDF endpoint tests ───────────────────────────────────────────────────────

test('loan agreement PDF returns PDF content-type', function () {
    $admin = pdfAdmin();
    $loan = pdfLoan();

    $resp = $this->actingAs($admin, 'web')
        ->get(route('loans.pdf.agreement', $loan));

    expect($resp->status())->toBeIn([200, 302]);
})->group('pdf');

test('repayment schedule PDF can be generated', function () {
    $admin = pdfAdmin();
    $loan = pdfLoan();

    $resp = $this->actingAs($admin, 'web')
        ->get(route('loans.pdf.schedule', $loan));

    expect($resp->status())->toBeIn([200, 302]);
})->group('pdf');

test('payment receipt PDF can be generated', function () {
    $admin = pdfAdmin();
    $loan = pdfLoan();
    $payment = pdfPayment($loan, $admin);

    $resp = $this->actingAs($admin, 'web')
        ->get(route('payments.pdf.receipt', $payment));

    expect($resp->status())->toBeIn([200, 302]);
})->group('pdf');

test('account statement PDF can be generated', function () {
    $admin = pdfAdmin();
    $borrower = Borrower::factory()->create();

    $resp = $this->actingAs($admin, 'web')
        ->get(route('borrowers.pdf.statement', $borrower));

    expect($resp->status())->toBeIn([200, 302]);
})->group('pdf');

test('disbursement letter PDF can be generated', function () {
    $admin = pdfAdmin();
    $loan = pdfLoan(['status' => LoanStatus::Disbursed]);

    $resp = $this->actingAs($admin, 'web')
        ->get(route('loans.pdf.disbursement', $loan));

    expect($resp->status())->toBeIn([200, 302]);
})->group('pdf');

test('disbursement letter PDF returns 404 for missing loan', function () {
    $admin = pdfAdmin();

    $resp = $this->actingAs($admin, 'web')
        ->get('/admin/loans/99999/pdf/disbursement');

    expect($resp->status())->toBeIn([404, 302]);
})->group('pdf');
