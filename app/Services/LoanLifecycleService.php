<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\LoanActionException;
use App\Jobs\AutoDisburseLoanJob;
use App\Jobs\CreateStandingOrdersJob;
use App\Jobs\DisburseMobileMoneyJob;
use App\Jobs\SendLoanEventNotificationJob;
use App\Models\Landlord\CrbInquiry;
use App\Models\Landlord\TenantWallet;
use App\Models\Tenant\FundBalance;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\LoanTopup;
use App\Models\Tenant\User;
use App\Services\Payment\DisbursementOutcomeService;
use Illuminate\Support\Facades\DB;

/**
 * Approving and disbursing a loan: the one place these happen, so the single-loan
 * buttons, Bulk Loans and any future caller all get the same checks and the same
 * side effects (schedule, fund, GL, payout, CRB, notifications).
 *
 * Each action re-reads the loan under a row lock and re-checks its status, so a
 * double click or two staff acting at once can't approve or disburse it twice.
 */
class LoanLifecycleService
{
    public function __construct(
        private readonly LoanCalculatorService $calculator,
        private readonly FundService $fund,
        private readonly ApprovalService $approvals,
        private readonly CrbService $crb,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Submitted → Approved.
     *
     * @throws LoanActionException
     */
    public function approve(Loan $loan, User $by, ?string $notes = null): Loan
    {
        $this->assertBorrowerMayBorrow($loan);

        if ($loan->status !== LoanStatus::Submitted) {
            throw LoanActionException::invalid('Only submitted loans can be approved.');
        }

        // The workspace's risk policies (Collections → Risk Policies). They used to run
        // only when someone asked, so a "block" rule blocked nothing. A block stands
        // until someone with approval rights overrides it with a reason; warnings
        // are recorded on the loan and don't stop it.
        $assessment = app(\App\Services\RiskAssessmentService::class)->assess($loan);
        $blocks = array_values(array_filter($assessment['flags'], fn ($f) => $f['severity'] === 'block'));
        if ($blocks) {
            throw new LoanActionException(
                'This application breaks '.count($blocks).' of your risk policies. Override each with a reason to approve it anyway.',
                409,
                'risk_block',
                ['risk_flags' => $blocks],
            );
        }

        $this->approvals->ensureApproved('loan_approval', $loan->id, $by, (float) $loan->principal_amount, 'approving this loan');

        $loan = DB::transaction(function () use ($loan, $by, $notes) {
            $locked = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== LoanStatus::Submitted) {
                throw LoanActionException::invalid("Loan {$locked->loan_number} is already {$locked->status->label()}.");
            }

            $locked->update([
                'status' => LoanStatus::Approved->value,
                'approved_by' => $by->id,
                'approval_date' => now()->toDateString(),
            ]);

            $this->logStatus($locked, LoanStatus::Submitted, LoanStatus::Approved, $by->id, $notes ?: 'Loan approved.');

            return $locked;
        });

        dispatch(new SendLoanEventNotificationJob($loan->id, 'approved'));

        $this->notifications->notifyRoles(
            ['branch_manager', 'super_admin'],
            'loan_approved',
            "Loan {$loan->loan_number} approved — awaiting disbursement",
            'Approved by '.$by->name.'. Principal: ZMW '.number_format((float) $loan->principal_amount, 2),
            ['loan_id' => $loan->id, 'loan_number' => $loan->loan_number],
        );

        return $loan->fresh();
    }

    /**
     * Approved → Active: schedule, fund debit, GL, then the payout (mobile money)
     * or the borrower notification (cash / bank, money already handed over).
     *
     * @param  array{disbursement_method: string, disbursement_date: string, disbursement_account?: ?string, disbursement_reference?: ?string, first_repayment_date?: ?string, notes?: ?string}  $data
     * @return array{loan: Loan, payout_sent: bool}
     *
     * @throws LoanActionException
     */
    public function disburse(Loan $loan, User $by, array $data, bool $crbOverride = false, ?string $crbOverrideReason = null): array
    {
        $this->assertBorrowerMayBorrow($loan);

        if ($loan->status !== LoanStatus::Approved) {
            throw LoanActionException::invalid('Only approved loans can be disbursed.');
        }

        $method = PaymentMethod::tryFrom($data['disbursement_method'] ?? '')
            ?? throw LoanActionException::invalid('Choose how the money was paid out.');

        $tenantId = (string) (tenant('id') ?? 'local');
        $wallet = null;
        if ((tenant('plan') ?? 'starter') === 'enterprise') {
            $wallet = TenantWallet::where('tenant_id', $tenantId)->where('is_active', true)->first();
        }
        $automaticPayout = (bool) $wallet?->disburse_enabled || $method->isMobileMoney();

        if ($automaticPayout && tenancy()->tenant && ! PlanFeatureService::forTenant()->has('disbursement_mobile_money')) {
            throw LoanActionException::invalid(
                'Mobile-money payouts aren\'t included in your plan. Pay the borrower yourself and record it as Cash or Bank transfer, or upgrade the plan.',
            );
        }

        $this->approvals->ensureApproved('loan_disbursement', $loan->id, $by, (float) $loan->principal_amount, 'disbursement');

        $this->checkCrb($loan, $tenantId, $crbOverride, $crbOverrideReason);

        $this->assertFundsAvailable($loan, (float) $loan->principal_amount);

        $loan = DB::transaction(function () use ($loan, $by, $data, $method) {
            $locked = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== LoanStatus::Approved) {
                throw LoanActionException::invalid("Loan {$locked->loan_number} is already {$locked->status->label()}.");
            }

            $plan = $locked->loanPlan ?? throw LoanActionException::invalid("Loan {$locked->loan_number} has no loan plan, so its schedule can't be built.");
            $disbursementDate = $data['disbursement_date'];
            $maturityDate = $this->calculator->maturityDate($disbursementDate, $plan, $locked->tenure);
            $firstRepaymentDate = ($data['first_repayment_date'] ?? null)
                ?: $this->calculator->maturityDate($disbursementDate, $plan, 1);

            $locked->update([
                'status' => LoanStatus::Active->value,
                'disbursed_by' => $by->id,
                'disbursement_date' => $disbursementDate,
                'disbursement_method' => $method->value,
                'disbursement_account' => $data['disbursement_account'] ?? null,
                'disbursement_reference' => $data['disbursement_reference'] ?? null,
                'first_repayment_date' => $firstRepaymentDate,
                'maturity_date' => $maturityDate,
            ]);

            // A loan reversed after a failed payout may already have rows; never stack a second schedule.
            LoanSchedule::where('loan_id', $locked->id)->delete();

            $rows = $this->calculator->generateSchedule(
                $plan,
                (float) $locked->principal_amount,
                $locked->tenure,
                $disbursementDate,
                (float) $locked->interest_amount,
                (float) $locked->processing_fee + (float) $locked->insurance_fee,
            );

            // The schedule must add up to what the loan says is owed; a few cents of
            // rounding between the interest formula and the instalments go on the last one.
            $rows = $this->alignWithBalance($rows, (string) $locked->outstanding_balance);

            foreach ($rows as $row) {
                LoanSchedule::create([
                    'loan_id' => $locked->id,
                    'instalment_number' => $row['instalment_number'],
                    'due_date' => $row['due_date'],
                    'principal_due' => $row['principal_due'],
                    'interest_due' => $row['interest_due'],
                    'fee_due' => $row['fee_due'],
                    'total_due' => $row['total_due'],
                    'outstanding' => $row['outstanding'],
                ]);
            }

            $this->logStatus($locked, LoanStatus::Approved, LoanStatus::Active, $by->id, ($data['notes'] ?? null) ?: 'Loan disbursed and activated.');

            $this->fund->recordDisbursement($locked->fresh(), $by->id);

            app(GlLedgerService::class)->postDisbursement($locked->fresh());

            $this->accrueAgentCommission($locked->fresh());

            return $locked->fresh(['borrower']);
        });

        // Mobile money: the borrower notification and CRB "loan opened" wait for the
        // provider to accept the payout; a rejected payout reverses this disbursement
        // (see DisbursementOutcomeService).
        $payout = match (true) {
            (bool) $wallet?->disburse_enabled => new AutoDisburseLoanJob($loan, $wallet->id),
            $method->isMobileMoney() => new DisburseMobileMoneyJob($loan),
            default => null,
        };

        if ($payout) {
            dispatch($payout);

            if ($wallet?->disburse_enabled && $wallet->debit_enabled) {
                dispatch(new CreateStandingOrdersJob($loan, $wallet->id));
            }

            return ['loan' => $loan, 'payout_sent' => true];
        }

        // Cash / bank transfer: the money has already been handed over.
        dispatch(new SendLoanEventNotificationJob($loan->id, 'disbursed'));
        app(DisbursementOutcomeService::class)->recordCrbLoanOpened($loan);

        return ['loan' => $loan, 'payout_sent' => false];
    }

    /**
     * The agent who brought the loan in earns their commission once it is disbursed
     * (pending until approved and paid under Agents → Commissions). Nothing created
     * these before, so the commissions screen was always empty. One per loan: a
     * payout that failed and was reversed reopens the same row.
     */
    private function accrueAgentCommission(Loan $loan): void
    {
        $agent = $loan->agent_id ? \App\Models\Tenant\Agent::find($loan->agent_id) : null;
        if (! $agent || $agent->status !== 'active') {
            return;
        }

        $existing = \App\Models\Tenant\AgentCommission::where('loan_id', $loan->id)->lockForUpdate()->first();
        if ($existing && $existing->status !== 'reversed') {
            return;
        }

        $values = [
            'agent_id' => $agent->id,
            'disbursed_amount' => $loan->principal_amount,
            'commission_amount' => $agent->calculateCommission((float) $loan->principal_amount),
            'status' => 'pending',
            'approved_by' => null,
        ];

        $existing ? $existing->update($values) : \App\Models\Tenant\AgentCommission::create(['loan_id' => $loan->id, ...$values]);
    }

    /**
     * New terms for a running loan: the unpaid principal is rescheduled over the
     * new tenure with fresh interest; interest and fees already owed are carried
     * onto the new instalments as they are (never turned into principal, so no
     * interest is charged on interest). Partly paid instalments are closed at what
     * was paid, so no payment disappears from the schedule.
     *
     * @throws LoanActionException
     */
    public function restructure(Loan $loan, User $by, int $tenure, string $reason): Loan
    {
        if (! in_array($loan->status, [LoanStatus::Active, LoanStatus::Disbursed, LoanStatus::Frozen, LoanStatus::Defaulted], true)) {
            throw LoanActionException::invalid('Only running loans (active, frozen or defaulted) can be restructured.');
        }
        if ($tenure < 1) {
            throw LoanActionException::invalid('The new tenure must be at least one period.');
        }

        return DB::transaction(function () use ($loan, $by, $tenure, $reason) {
            $locked = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            $this->reschedule($locked, $tenure, '0');

            $this->logStatus($locked, $from, $locked->status, $by->id, "Restructured over {$tenure} period(s): {$reason}");

            activity()->performedOn($locked)->causedBy($by)
                ->withProperties(['reason' => $reason, 'new_tenure' => $tenure])
                ->log('restructured');

            return $locked->fresh(['schedule']);
        });
    }

    /**
     * Approve a top-up: lend the extra amount (recorded as paid out by cash or bank
     * transfer, debiting the fund and the ledger like any disbursement) and
     * reschedule the loan over the new tenure.
     *
     * @param  array{disbursement_method: string, disbursement_date?: ?string, disbursement_reference?: ?string}  $payout
     *
     * @throws LoanActionException
     */
    public function approveTopUp(Loan $loan, LoanTopup $topup, User $by, array $payout): Loan
    {
        if ($topup->loan_id !== $loan->id) {
            throw new LoanActionException('This top-up belongs to another loan.', 404);
        }
        if (! in_array($loan->status, [LoanStatus::Active, LoanStatus::Disbursed], true)) {
            throw LoanActionException::invalid('Only active loans can be topped up.');
        }
        if (! in_array($payout['disbursement_method'] ?? null, ['cash', 'bank_transfer'], true)) {
            throw LoanActionException::invalid('Pay the top-up out by cash or bank transfer and record it here.');
        }

        $this->assertBorrowerMayBorrow($loan);

        $amount = bcadd((string) $topup->topup_amount, '0', 2);
        $this->approvals->ensureApproved('loan_disbursement', $loan->id, $by, (float) $amount, 'paying out this top-up');
        $this->assertFundsAvailable($loan, (float) $amount);

        return DB::transaction(function () use ($loan, $topup, $by, $payout, $amount) {
            $locked = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();
            $topup = LoanTopup::whereKey($topup->id)->lockForUpdate()->firstOrFail();

            if ($topup->status !== 'pending') {
                throw LoanActionException::invalid('This top-up has already been decided.');
            }

            $tenure = (int) ($topup->new_tenure ?: $locked->tenure);
            $date = $payout['disbursement_date'] ?? now()->toDateString();

            $locked->update(['principal_amount' => bcadd((string) $locked->principal_amount, $amount, 2)]);
            $this->reschedule($locked, $tenure, $amount);

            $topup->update(['status' => 'approved', 'approved_by' => $by->id, 'approved_at' => now()]);

            // The extra money leaves the fund and is booked as a receivable.
            $this->fund->debit((float) $amount, \App\Enums\FundTransactionType::Disburse, $locked, $by->id, "Loan top-up: {$locked->loan_number}");
            app(GlLedgerService::class)->post(
                "Loan top-up: {$locked->loan_number}",
                [
                    ['account_code' => '1100', 'side' => 'debit', 'amount' => (float) $amount, 'notes' => 'Top-up lent'],
                    ['account_code' => '1001', 'side' => 'credit', 'amount' => (float) $amount, 'notes' => 'Top-up paid out ('.str_replace('_', ' ', $payout['disbursement_method']).')'],
                ],
                $locked,
                $date,
                $by->id,
            );

            $this->logStatus($locked, $locked->status, $locked->status, $by->id,
                'Top-up of K '.number_format((float) $amount, 2).' approved and paid by '.str_replace('_', ' ', $payout['disbursement_method'])
                .(! empty($payout['disbursement_reference']) ? " (ref {$payout['disbursement_reference']})" : '').'.');

            activity()->performedOn($locked)->causedBy($by)
                ->withProperties(['topup_amount' => (float) $amount, 'new_tenure' => $tenure])
                ->log('topup_approved');

            return $locked->fresh(['schedule']);
        });
    }

    /**
     * Rebuild the unpaid part of a loan's schedule from today (caller holds the lock).
     * $additionalPrincipal is new money lent (a top-up), already added to principal_amount.
     */
    private function reschedule(Loan $loan, int $tenure, string $additionalPrincipal): void
    {
        $plan = $loan->loanPlan ?? throw LoanActionException::invalid("Loan {$loan->loan_number} has no loan plan, so it can't be rescheduled.");

        $principalLeft = $additionalPrincipal;
        $interestLeft = '0';
        $feeLeft = '0';

        foreach (LoanSchedule::where('loan_id', $loan->id)->where('is_paid', false)->lockForUpdate()->get() as $row) {
            $principalLeft = bcadd($principalLeft, $this->remaining($row->principal_due, $row->principal_paid), 2);
            $interestLeft = bcadd($interestLeft, $this->remaining($row->interest_due, $row->interest_paid), 2);
            $feeLeft = bcadd($feeLeft, $this->remaining($row->fee_due, $row->fee_paid), 2);

            if (bccomp((string) $row->total_paid, '0', 2) > 0) {
                // Close a partly paid instalment at what was paid; the rest moves on.
                $row->update([
                    'principal_due' => $row->principal_paid,
                    'interest_due' => $row->interest_paid,
                    'fee_due' => $row->fee_paid,
                    'total_due' => $row->total_paid,
                    'outstanding' => 0,
                    'is_paid' => true,
                    'paid_date' => $row->paid_date ?? now()->toDateString(),
                ]);
            } else {
                $row->delete();
            }
        }

        if (bccomp($principalLeft, '0', 2) <= 0 && bccomp($interestLeft, '0', 2) <= 0 && bccomp($feeLeft, '0', 2) <= 0) {
            throw LoanActionException::invalid('Nothing is left to reschedule on this loan.');
        }

        $newInterest = $this->calculator->calculateAmounts($plan, (float) $principalLeft, $tenure)['interest_amount'];
        $rows = $this->calculator->generateSchedule($plan, (float) $principalLeft, $tenure, now()->toDateString(), (float) $newInterest);
        $rows = $this->calculator->spread($rows, 'interest_due', (float) $interestLeft);
        $rows = $this->calculator->spread($rows, 'fee_due', (float) $feeLeft);

        $next = (int) (LoanSchedule::where('loan_id', $loan->id)->max('instalment_number') ?? 0);
        foreach ($rows as $i => $row) {
            LoanSchedule::create([
                'loan_id' => $loan->id,
                'instalment_number' => $next + $i + 1,
                'due_date' => $row['due_date'],
                'principal_due' => $row['principal_due'],
                'interest_due' => $row['interest_due'],
                'fee_due' => $row['fee_due'],
                'total_due' => $row['total_due'],
                'outstanding' => $row['outstanding'],
            ]);
        }

        $all = LoanSchedule::where('loan_id', $loan->id)->get();
        // Paid instalments owe nothing, whatever their outstanding column says.
        $newOutstanding = bcadd((string) $all->where('is_paid', false)->sum(fn ($r) => (float) $r->outstanding), '0', 2);

        $loan->update([
            'tenure' => $tenure,
            'interest_amount' => bcadd((string) $all->sum(fn ($r) => (float) $r->interest_due), '0', 2),
            'total_payable' => bcadd((string) $all->sum(fn ($r) => (float) $r->total_due), '0', 2),
            'outstanding_balance' => $newOutstanding,
            'maturity_date' => $all->max('due_date'),
        ]);
    }

    private function remaining(mixed $due, mixed $paid): string
    {
        $left = bcsub(bcadd((string) $due, '0', 2), bcadd((string) $paid, '0', 2), 2);

        return bccomp($left, '0', 2) > 0 ? $left : '0';
    }

    /**
     * A blacklisted or deactivated borrower can't be given a loan, whatever stage it's at.
     *
     * @throws LoanActionException
     */
    public function assertBorrowerMayBorrow(Loan $loan): void
    {
        $borrower = $loan->borrower;

        if (! $borrower) {
            throw LoanActionException::invalid('This loan has no borrower.');
        }
        if ($borrower->is_blacklisted) {
            throw LoanActionException::invalid("{$borrower->full_name} is blacklisted".($borrower->blacklist_reason ? " ({$borrower->blacklist_reason})" : '').'. Remove them from the blacklist before going ahead.');
        }
        if ($borrower->is_active === false) {
            throw LoanActionException::invalid("{$borrower->full_name}'s account is deactivated.");
        }
    }

    /**
     * Lending money the fund doesn't hold is refused, unless the workspace has
     * switched on Settings → allow_negative_fund_balance.
     *
     * @throws LoanActionException
     */
    private function assertFundsAvailable(Loan $loan, float $amount): void
    {
        $allowNegative = filter_var(
            DB::table('settings')->where('key', 'allow_negative_fund_balance')->value('value'),
            FILTER_VALIDATE_BOOLEAN,
        );

        if ($allowNegative) {
            return;
        }

        $available = (float) (FundBalance::current()->available_balance ?? 0);

        if (bccomp((string) $available, (string) $amount, 2) < 0) {
            throw LoanActionException::invalid(sprintf(
                'Not enough in the fund for %s: K %s needed, K %s available. Record a fund deposit first (Funding → Fund Management).',
                $loan->loan_number,
                number_format($amount, 2),
                number_format($available, 2),
            ));
        }
    }

    /** Put any rounding difference between schedule and loan balance on the last instalment's interest. */
    private function alignWithBalance(array $rows, string $balance): array
    {
        if (! $rows) {
            return $rows;
        }

        $scheduled = '0';
        foreach ($rows as $row) {
            $scheduled = bcadd($scheduled, (string) $row['total_due'], 2);
        }

        $diff = bcsub(bcadd($balance, '0', 2), $scheduled, 2);

        // Only cents of rounding; anything larger means the loan's figures are off and are left as they are.
        if (bccomp($diff, '0', 2) === 0 || bccomp(ltrim($diff, '-'), '1.00', 2) > 0) {
            return $rows;
        }

        $last = array_key_last($rows);
        $rows[$last]['interest_due'] = (float) bcadd((string) $rows[$last]['interest_due'], $diff, 2);
        $rows[$last]['total_due'] = (float) bcadd((string) $rows[$last]['total_due'], $diff, 2);
        $rows[$last]['outstanding'] = (float) bcadd((string) $rows[$last]['outstanding'], $diff, 2);

        return $rows;
    }

    /**
     * Borrowers with active loans at another lender need an explicit override.
     *
     * @throws LoanActionException
     */
    private function checkCrb(Loan $loan, string $tenantId, bool $override, ?string $reason): void
    {
        $identifier = $loan->borrower->crbIdentifier();

        if (! $identifier) {
            return;
        }

        $hash = $this->crb->hash($identifier['value'], $identifier['type']);

        if ($override) {
            CrbInquiry::create([
                'identity_hash' => $hash,
                'tenant_id' => $tenantId,
                'purpose' => 'loan_disbursement',
                'result_has_active_loans' => true,
                'override_requested' => true,
                'override_reason' => $reason ?: 'Manual override',
                'created_at' => now(),
            ]);

            return;
        }

        $result = $this->crb->check($hash, $identifier['type'], $tenantId, 'loan_disbursement');

        if ($result['has_active_loans']) {
            throw LoanActionException::crbAlert([
                'credit_score' => $result['credit_score'],
                'score_band' => $result['score_band'],
                'active_loan_count' => $result['active_loan_count'],
                'risk_level' => $result['risk_level'],
            ]);
        }
    }

    private function logStatus(Loan $loan, ?LoanStatus $from, LoanStatus $to, ?int $userId, ?string $notes): void
    {
        $loan->statusLogs()->create([
            'changed_by' => $userId,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'notes' => $notes,
        ]);
    }
}
