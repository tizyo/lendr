<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Payment;
use App\Services\LoanCalculatorService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Staff-facing PDF documents. Every template extends resources/views/pdf/layout,
 * which brands the document with the lender's own name, registration details,
 * logo and colour (App\Support\DocumentBrand) and a small "Powered by LENDR" footer.
 */
class PdfController extends Controller
{
    public function __construct(private readonly LoanCalculatorService $calculator) {}

    public function loanApplication(Loan $loan): Response
    {
        $loan->load(['borrower', 'loanType:id,name', 'loanPlan:id,name', 'createdBy:id,name']);
        $borrower = $loan->borrower;

        $loanData = [
            ...$this->loanBasics($loan),
            'created_by' => $loan->createdBy?->name,
            'borrower' => [
                'name' => $borrower->full_name,
                'borrower_type' => $borrower->borrower_type,
                'borrower_number' => $borrower->borrower_number,
                'date_of_birth' => $borrower->date_of_birth?->format('d M Y'),
                'gender' => $borrower->gender,
                'national_id' => $borrower->national_id,
                'phone' => $borrower->phone,
                'email' => $borrower->email,
                'address' => $borrower->address,
                'city' => $borrower->city,
                'province' => $borrower->province,
                'occupation' => $borrower->occupation,
                'employer' => $borrower->employer,
                'next_of_kin_name' => $borrower->next_of_kin_name,
                'next_of_kin_phone' => $borrower->next_of_kin_phone,
                'next_of_kin_relationship' => $borrower->next_of_kin_relationship,
                // Corporate borrowers
                'trading_name' => $borrower->trading_name,
                'business_type' => Borrower::BUSINESS_TYPES[$borrower->business_type] ?? null,
                'industry' => $borrower->industry,
                'company_reg_number' => $borrower->company_reg_number,
                'tpin_number' => $borrower->tpin_number,
                'incorporation_date' => $borrower->incorporation_date?->format('d M Y'),
                'contact_person_name' => $borrower->contact_person_name,
                'contact_person_position' => $borrower->contact_person_position,
                'contact_person_phone' => $borrower->contact_person_phone,
            ],
        ];

        return $this->download('pdf.loan-application', ['loan' => $loanData], "loan-application-{$loan->loan_number}.pdf");
    }

    public function loanAgreement(Loan $loan): Response
    {
        $loan->load(['borrower', 'loanType:id,name', 'loanPlan', 'approvedBy:id,name', 'schedule']);

        [$schedule, $projected] = $this->scheduleRows($loan);

        $loanData = [
            ...$this->loanBasics($loan),
            'interest_amount' => $this->money($loan->interest_amount),
            'processing_fee' => $this->money($loan->processing_fee),
            'insurance_fee' => $this->money($loan->insurance_fee),
            'total_payable' => $this->money($loan->total_payable),
            'interest_rate' => $loan->interest_rate,
            'interest_type' => $loan->interest_type,
            'approval_date' => $loan->approval_date?->format('d M Y'),
            'disbursement_date' => $loan->disbursement_date?->format('d M Y'),
            'first_repayment_date' => $loan->first_repayment_date?->format('d M Y'),
            'maturity_date' => $loan->maturity_date?->format('d M Y'),
            'approved_by' => $loan->approvedBy?->name,
            'borrower' => [
                'name' => $loan->borrower->full_name,
                'borrower_number' => $loan->borrower->borrower_number,
                'phone' => $loan->borrower->phone,
                'city' => $loan->borrower->city,
                'kyc_verified' => (bool) $loan->borrower->kyc_verified,
            ],
            'schedule' => $schedule,
            'schedule_projected' => $projected,
        ];

        return $this->download('pdf.loan-agreement', ['loan' => $loanData], "loan-agreement-{$loan->loan_number}.pdf");
    }

    public function paymentReceipt(Payment $payment): Response
    {
        $payment->load(['loan.borrower', 'loan.loanType:id,name', 'recordedBy:id,name']);

        $paymentData = [
            'receipt_number' => $payment->receipt_number,
            'loan_number' => $payment->loan->loan_number,
            'borrower_name' => $payment->loan->borrower->full_name,
            'borrower_number' => $payment->loan->borrower->borrower_number,
            'loan_type' => $payment->loan->loanType->name,
            'amount' => $this->money($payment->amount),
            'principal_allocated' => $this->money($payment->principal_allocated),
            'interest_allocated' => $this->money($payment->interest_allocated),
            'penalty_allocated' => $this->money($payment->penalty_allocated),
            'payment_method' => $payment->payment_method->label(),
            'payment_date' => $payment->payment_date->format('d M Y'),
            'reference' => $payment->reference,
            'notes' => $payment->notes,
            'recorded_by' => $payment->recordedBy?->name,
            'loan_balance' => $this->money($payment->loan->outstanding_balance),
        ];

        return $this->download('pdf.payment-receipt', ['payment' => $paymentData], "receipt-{$payment->receipt_number}.pdf");
    }

    public function repaymentSchedule(Loan $loan): Response
    {
        $loan->load(['borrower:id,first_name,last_name,borrower_type,company_name,borrower_number', 'loanType:id,name', 'loanPlan:id,name', 'schedule']);

        $loanData = [
            ...$this->loanBasics($loan),
            'status_label' => $loan->status->label(),
            'total_payable' => $this->money($loan->total_payable),
            'total_paid' => $this->money($loan->total_paid),
            'outstanding_balance' => $this->money($loan->outstanding_balance),
            'penalty_balance' => $this->money($loan->penalty_balance),
            'interest_rate' => $loan->interest_rate,
            'interest_type' => $loan->interest_type,
            'first_repayment_date' => $loan->first_repayment_date?->format('d M Y'),
            'maturity_date' => $loan->maturity_date?->format('d M Y'),
            'borrower' => [
                'name' => $loan->borrower->full_name,
                'borrower_number' => $loan->borrower->borrower_number,
            ],
            'schedule' => $loan->schedule->map(fn ($s) => [
                'instalment_number' => $s->instalment_number,
                'due_date' => $s->due_date->format('d M Y'),
                'principal_due' => $this->money($s->principal_due),
                'interest_due' => $this->money($s->interest_due),
                'total_due' => $this->money($s->total_due),
                'total_paid' => $this->money($s->total_paid),
                'outstanding' => $this->money($s->outstanding),
                'is_paid' => $s->is_paid,
                'days_overdue' => $s->days_overdue,
            ])->all(),
        ];

        return $this->download('pdf.repayment-schedule', ['loan' => $loanData], "schedule-{$loan->loan_number}.pdf");
    }

    public function disbursementLetter(Loan $loan): Response
    {
        $loan->load(['borrower', 'loanType:id,name', 'loanPlan:id,name', 'disbursedBy:id,name']);

        $loanData = [
            ...$this->loanBasics($loan),
            'disbursement_method' => $loan->disbursement_method?->label(),
            'disbursement_account' => $loan->disbursement_account,
            'disbursement_date' => $loan->disbursement_date?->format('d M Y'),
            'first_repayment_date' => $loan->first_repayment_date?->format('d M Y'),
            'maturity_date' => $loan->maturity_date?->format('d M Y'),
            'interest_rate' => $loan->interest_rate,
            'interest_type' => $loan->interest_type,
            'disbursed_by' => $loan->disbursedBy?->name,
            'borrower' => [
                'name' => $loan->borrower->full_name,
                'borrower_number' => $loan->borrower->borrower_number,
                'phone' => $loan->borrower->phone,
                'email' => $loan->borrower->email,
                'city' => $loan->borrower->city,
            ],
        ];

        return $this->download('pdf.disbursement-letter', ['loan' => $loanData], "disbursement-{$loan->loan_number}.pdf");
    }

    public function accountStatement(Borrower $borrower): Response
    {
        $borrower->load(['loans' => fn ($q) => $q->with(['loanType:id,name', 'payments'])]);
        $currency = tenancy()->tenant?->currency ?? 'ZMW';

        $loans = $borrower->loans->map(fn ($loan) => [
            'loan_number' => $loan->loan_number,
            'loan_type' => $loan->loanType->name,
            'status' => $loan->status->value,
            'status_label' => $loan->status->label(),
            'currency' => $currency,
            'principal_amount' => $this->money($loan->principal_amount),
            'total_payable' => $this->money($loan->total_payable),
            'total_paid' => $this->money($loan->total_paid),
            'outstanding_balance' => $this->money($loan->outstanding_balance),
            'application_date' => $loan->application_date?->format('d M Y'),
            'disbursement_date' => $loan->disbursement_date?->format('d M Y'),
            'maturity_date' => $loan->maturity_date?->format('d M Y'),
            'payments' => $loan->payments->map(fn ($p) => [
                'receipt_number' => $p->receipt_number,
                'payment_date' => $p->payment_date->format('d M Y'),
                'payment_method' => $p->payment_method->label(),
                'amount' => $this->money($p->amount),
                'principal_allocated' => $this->money($p->principal_allocated),
                'interest_allocated' => $this->money($p->interest_allocated),
            ])->all(),
        ])->all();

        return $this->download('pdf.account-statement', [
            'borrower' => [
                'name' => $borrower->full_name,
                'borrower_number' => $borrower->borrower_number,
                'phone' => $borrower->phone,
                'email' => $borrower->email,
                'city' => $borrower->city,
                'kyc_verified' => $borrower->kyc_verified,
                'credit_score' => $borrower->credit_score,
            ],
            'loans' => $loans,
            'summary' => [
                'total_disbursed' => $borrower->loans->sum(fn ($l) => (float) $l->principal_amount),
                'total_paid' => $borrower->loans->sum(fn ($l) => (float) $l->total_paid),
                'outstanding' => $borrower->loans->sum(fn ($l) => (float) $l->outstanding_balance),
            ],
            'currency' => $currency,
        ], "statement-{$borrower->borrower_number}.pdf");
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Fields every loan document shows. */
    private function loanBasics(Loan $loan): array
    {
        return [
            'loan_number' => $loan->loan_number,
            'loan_type' => $loan->loanType?->name ?? '—',
            'loan_plan' => $loan->loanPlan?->name ?? '—',
            'currency' => tenancy()->tenant?->currency ?? 'ZMW',
            'principal_amount' => $this->money($loan->principal_amount),
            'tenure' => $loan->tenure,
            'tenure_type' => $loan->tenure_type,
            'repayment_schedule' => $loan->repayment_schedule,
            'application_date' => $loan->application_date?->format('d M Y'),
            'loan_purpose' => $loan->loan_purpose,
            'collateral_description' => $loan->collateral_description,
            'guarantor_name' => $loan->guarantor_name,
            'guarantor_phone' => $loan->guarantor_phone,
            'guarantor_relationship' => $loan->guarantor_relationship,
        ];
    }

    /**
     * The loan's instalments. Before disbursement there are none yet, so an agreement
     * shows the schedule the loan would get if paid out today, marked as projected.
     *
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    private function scheduleRows(Loan $loan): array
    {
        if ($loan->schedule->isNotEmpty()) {
            return [$loan->schedule->map(fn ($s) => [
                'instalment_number' => $s->instalment_number,
                'due_date' => $s->due_date->format('d M Y'),
                'principal_due' => $this->money($s->principal_due),
                'interest_due' => $this->money($s->interest_due),
                'total_due' => $this->money($s->total_due),
                'is_paid' => $s->is_paid,
                'days_overdue' => $s->days_overdue,
            ])->all(), false];
        }

        if (! $loan->loanPlan) {
            return [[], false];
        }

        $rows = rescue(fn () => $this->calculator->generateSchedule(
            $loan->loanPlan,
            (float) $loan->principal_amount,
            (int) $loan->tenure,
            now()->toDateString(),
            (float) $loan->interest_amount,
        ), [], false);

        return [array_map(fn ($r) => [
            'instalment_number' => $r['instalment_number'],
            'due_date' => \Illuminate\Support\Carbon::parse($r['due_date'])->format('d M Y'),
            'principal_due' => $this->money($r['principal_due']),
            'interest_due' => $this->money($r['interest_due']),
            'total_due' => $this->money($r['total_due']),
            'is_paid' => false,
            'days_overdue' => 0,
        ], $rows), $rows !== []];
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2);
    }

    private function download(string $view, array $data, string $filename): Response
    {
        return Pdf::loadView($view, [...$data, 'generatedAt' => now()->format('d M Y H:i')])
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }
}
