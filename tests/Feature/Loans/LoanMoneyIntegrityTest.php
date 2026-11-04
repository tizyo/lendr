<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Exceptions\LoanActionException;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\FundBalance;
use App\Models\Tenant\GlAccount;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\Payment;
use App\Models\Tenant\User;
use App\Services\LoanLifecycleService;
use App\Services\Payment\PaymentService;
use Illuminate\Support\Facades\DB;

/*
 * The money paths end to end: lending money the fund holds, never twice, never to
 * a blacklisted borrower; schedules that add up to what's owed; payments split
 * instalment by instalment (no interest counted twice); overpayments; exact
 * reversals; write-offs and the ledger staying balanced throughout.
 */

beforeEach(function () {
    $this->admin = actingAsAdmin();
    $this->actingAs($this->admin);
});

/** An approved loan on a flat plan: 5,000 principal, 1,500 interest, 100 fee → 6,600 owed. */
function integrityLoan(array $attrs = []): Loan
{
    return Loan::factory()->approved()->create(['loan_plan_id' => makePlan()->id, ...$attrs]);
}

function disburse(Loan $loan, ?User $by = null, ?string $date = null): Loan
{
    return app(LoanLifecycleService::class)->disburse($loan, $by ?? User::first(), [
        'disbursement_method' => 'cash',
        'disbursement_date' => $date ?? now()->subMonth()->toDateString(),
    ])['loan'];
}

function pay(Loan $loan, float $amount, array $extra = [], bool $allowOver = false): Payment
{
    return app(PaymentService::class)->record($loan, [
        'amount' => $amount,
        'payment_method' => 'cash',
        'payment_date' => now()->toDateString(),
        'source' => 'manual',
        'recorded_by' => User::first()->id,
        ...$extra,
    ], $allowOver);
}

function glBalance(string $code): float
{
    return round(GlAccount::where('code', $code)->firstOrFail()->balance(), 2);
}

function trialBalances(): bool
{
    $debits = (float) DB::table('gl_journal_lines')->where('side', 'debit')->sum('amount');
    $credits = (float) DB::table('gl_journal_lines')->where('side', 'credit')->sum('amount');

    return abs($debits - $credits) < 0.01;
}

// ─── Disbursement ────────────────────────────────────────────────────────────

it('refuses to lend money the fund does not hold', function () {
    $loan = integrityLoan();

    expect(fn () => disburse($loan))->toThrow(LoanActionException::class, 'Not enough in the fund');
    expect($loan->fresh()->status)->toBe(LoanStatus::Approved)
        ->and(LoanSchedule::where('loan_id', $loan->id)->count())->toBe(0);
});

it('lets a workspace that chose to allow a negative fund lend anyway', function () {
    DB::table('settings')->updateOrInsert(['key' => 'allow_negative_fund_balance'], ['value' => '1']);

    expect(disburse(integrityLoan())->status)->toBe(LoanStatus::Active);
});

it('never lends to a blacklisted borrower', function () {
    fundTheBook();
    $borrower = Borrower::factory()->create(['is_blacklisted' => true, 'blacklist_reason' => 'Fraud']);

    expect(fn () => Loan::factory()->create(['borrower_id' => $borrower->id]))->toThrow(LoanActionException::class);

    $clean = Borrower::factory()->create();
    $loan = integrityLoan(['borrower_id' => $clean->id]);
    $clean->update(['is_blacklisted' => true]);

    expect(fn () => disburse($loan))->toThrow(LoanActionException::class, 'blacklisted');
});

it('disburses once even if asked twice', function () {
    fundTheBook(10_000);
    $loan = integrityLoan();

    disburse($loan);
    expect(fn () => disburse($loan))->toThrow(LoanActionException::class);

    expect(LoanSchedule::where('loan_id', $loan->id)->count())->toBe(6)
        ->and((float) FundBalance::current()->available_balance)->toBe(5000.0)
        ->and(glBalance('1100'))->toBe(5000.0);
});

it('builds a schedule that adds up to everything owed, fees included', function () {
    fundTheBook();
    $loan = disburse(integrityLoan());

    $rows = LoanSchedule::where('loan_id', $loan->id)->get();
    expect(round((float) $rows->sum('total_due'), 2))->toBe(6600.0)
        ->and(round((float) $rows->sum('fee_due'), 2))->toBe(100.0)
        ->and(round((float) $rows->sum('principal_due'), 2))->toBe(5000.0);
});

it('runs bulk disbursement through the same checks and postings', function () {
    fundTheBook();
    $loans = collect([integrityLoan(), integrityLoan()]);

    $this->postJson(route('bulk.loans.disburse'), [
        'loan_ids' => $loans->pluck('id')->all(),
        'disbursement_method' => 'cash',
        'disbursement_date' => now()->toDateString(),
    ])->assertOk()->assertJsonPath('disbursed', 2);

    foreach ($loans as $loan) {
        expect(LoanSchedule::where('loan_id', $loan->id)->count())->toBe(6)
            ->and($loan->fresh()->status)->toBe(LoanStatus::Active);
    }
    expect(glBalance('1100'))->toBe(10000.0);
});

// ─── Payments ────────────────────────────────────────────────────────────────

it('splits payments instalment by instalment without counting interest twice', function () {
    fundTheBook();
    $loan = disburse(integrityLoan());
    $first = LoanSchedule::where('loan_id', $loan->id)->orderBy('instalment_number')->first();

    // Each instalment: 250 interest, 16.67 fee, 833.33 principal = 1,100.
    $a = pay($loan, 300);   // interest 250, fee 16.67, principal 33.33
    $b = pay($loan, 300);   // the rest of instalment 1 (800) — no interest again

    expect((float) $a->interest_allocated)->toBe(250.0)
        ->and((float) $b->interest_allocated)->toBe(0.0)
        ->and((float) $b->principal_allocated)->toBe(300.0)
        ->and((float) $first->fresh()->interest_paid)->toBe(250.0)
        ->and(glBalance('4001'))->toBe(250.0)
        ->and(trialBalances())->toBeTrue();
});

it('books penalties as penalty income, not as principal', function () {
    fundTheBook();
    $loan = disburse(integrityLoan());
    $loan->update(['penalty_balance' => 50]);

    $payment = pay($loan, 100);

    expect((float) $payment->penalty_allocated)->toBe(50.0)
        ->and(glBalance('4003'))->toBe(50.0)
        ->and(glBalance('1100'))->toBe(5000.0) // 50 left went to the instalment's interest
        ->and(trialBalances())->toBeTrue();
});

it('refuses a staff payment larger than the loan owes', function () {
    fundTheBook();
    $loan = disburse(integrityLoan());

    expect(fn () => pay($loan, 7000))->toThrow(LoanActionException::class, 'more than');
    expect(Payment::count())->toBe(0);
});

it('keeps an overpayment already received as a customer credit', function () {
    fundTheBook();
    $loan = disburse(integrityLoan());

    $payment = pay($loan, 7000, allowOver: true);

    expect((float) $payment->unallocated_amount)->toBe(400.0)
        ->and($loan->fresh()->status)->toBe(LoanStatus::Completed)
        ->and(glBalance('2100'))->toBe(400.0)
        ->and(glBalance('1100'))->toBe(0.0)
        ->and(trialBalances())->toBeTrue();
});

it('rejects payments dated in the future, before disbursement, or posted twice', function () {
    fundTheBook();
    $loan = disburse(integrityLoan(), date: now()->subDays(3)->toDateString());

    expect(fn () => pay($loan, 100, ['payment_date' => now()->addDay()->toDateString()]))->toThrow(LoanActionException::class, 'future')
        ->and(fn () => pay($loan, 100, ['payment_date' => now()->subDays(10)->toDateString()]))->toThrow(LoanActionException::class, 'before the loan');

    pay($loan, 100, ['reference' => 'BANK-1']);
    expect(fn () => pay($loan, 100, ['reference' => 'BANK-1']))->toThrow(LoanActionException::class, 'already recorded');
});

it('reverses a payment exactly: schedule, balance, fund and ledger', function () {
    fundTheBook();
    $loan = disburse(integrityLoan());
    $fundBefore = (float) FundBalance::current()->available_balance;
    $scheduleBefore = LoanSchedule::where('loan_id', $loan->id)->orderBy('id')->get(['interest_paid', 'principal_paid', 'fee_paid', 'outstanding', 'is_paid'])->toArray();

    $payment = pay($loan, 1500);
    app(PaymentService::class)->reverse($payment, $this->admin->id, 'Wrong loan');

    $loan->refresh();
    expect((float) $loan->outstanding_balance)->toBe(6600.0)
        ->and((float) $loan->total_paid)->toBe(0.0)
        ->and(LoanSchedule::where('loan_id', $loan->id)->orderBy('id')->get(['interest_paid', 'principal_paid', 'fee_paid', 'outstanding', 'is_paid'])->toArray())->toBe($scheduleBefore)
        ->and((float) FundBalance::current()->available_balance)->toBe($fundBefore)
        ->and(glBalance('1100'))->toBe(5000.0)
        ->and(glBalance('4001'))->toBe(0.0)
        ->and(trialBalances())->toBeTrue()
        ->and(Payment::withTrashed()->find($payment->id)->reversal_reason)->toBe('Wrong loan');

    expect(fn () => app(PaymentService::class)->reverse($payment, $this->admin->id, 'again'))->toThrow(LoanActionException::class);
});

it('reopens a fully paid loan when its last payment is reversed', function () {
    fundTheBook();
    $loan = disburse(integrityLoan());
    $payment = pay($loan, 6600);
    expect($loan->fresh()->status)->toBe(LoanStatus::Completed);

    app(PaymentService::class)->reverse($payment, $this->admin->id, 'Bounced');

    expect($loan->fresh()->status)->toBe(LoanStatus::Active)
        ->and($loan->fresh()->closed_date)->toBeNull();
});

// ─── Write-off, restructure ──────────────────────────────────────────────────

it('writes off only the principal still on the books', function () {
    fundTheBook();
    $loan = disburse(integrityLoan());
    pay($loan, 1100); // 250 interest, 16.67 fee, 833.33 principal
    $loan->fresh()->update(['status' => LoanStatus::Defaulted->value]);

    $this->postJson(route('api.v1.loans.write-off', $loan), ['reason' => 'Absconded'])->assertOk();

    expect(glBalance('1100'))->toBe(0.0)
        ->and(glBalance('5003'))->toBe(4166.67)
        ->and(trialBalances())->toBeTrue();

    $this->postJson(route('api.v1.loans.write-off', $loan), ['reason' => 'Again'])->assertUnprocessable();
});

it('restructures without charging interest on interest or losing part-payments', function () {
    fundTheBook();
    $loan = disburse(integrityLoan());
    pay($loan, 300); // part of instalment 1

    $loan = app(LoanLifecycleService::class)->restructure($loan->fresh(), $this->admin, 12, 'Hardship');

    $rows = LoanSchedule::where('loan_id', $loan->id)->orderBy('instalment_number')->get();
    $paidPart = $rows->first();

    expect((float) $paidPart->total_due)->toBe(300.0)   // closed at what was paid
        ->and($paidPart->is_paid)->toBeTrue()
        ->and(round((float) $rows->where('is_paid', false)->sum('principal_due'), 2))->toBe(round(5000 - 33.33, 2))
        ->and(round((float) $rows->where('is_paid', false)->sum('outstanding'), 2))->toBe(round((float) $loan->outstanding_balance, 2));
});

// ─── Approvals and expenses ──────────────────────────────────────────────────

it('holds a disbursement for its approval workflow, created on the Approvals screen', function () {
    fundTheBook();
    \App\Models\Tenant\ApprovalWorkflow::create([
        'name' => 'All loans', 'entity_type' => 'loan_disburse', // the old screen value
        'required_roles' => ['super_admin'], 'required_approvals' => 1, 'is_active' => true,
    ]);
    $loan = integrityLoan();

    expect(fn () => disburse($loan))->toThrow(LoanActionException::class, 'Approval required');
    expect(\App\Models\Tenant\ApprovalRequest::where('entity_id', $loan->id)->where('status', 'pending')->count())->toBe(1);
});

it('approves an expense once, and not by the person who submitted it', function () {
    fundTheBook();
    $submitter = User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
    $category = \App\Models\Tenant\ExpenseCategory::create(['name' => 'Rent', 'code' => 'RENT-'.uniqid()]);
    $expense = \App\Models\Tenant\Expense::create([
        'expense_number' => 'EXP-1', 'expense_category_id' => $category->id, 'amount' => 500,
        'expense_date' => now()->toDateString(), 'title' => 'Office rent', 'status' => 'pending',
        'submitted_by' => $submitter->id,
    ]);
    $fundBefore = (float) FundBalance::current()->available_balance;

    $this->actingAs($submitter)->postJson(route('api.v1.expenses.approve', $expense))->assertUnprocessable();

    $this->actingAs($this->admin)->postJson(route('api.v1.expenses.approve', $expense))->assertOk();
    $this->actingAs($this->admin)->postJson(route('api.v1.expenses.approve', $expense))->assertUnprocessable();

    expect((float) FundBalance::current()->available_balance)->toBe($fundBefore - 500);
});
