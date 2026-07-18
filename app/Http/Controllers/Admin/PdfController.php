<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Landlord\PlatformBranding;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PdfController extends Controller
{
    private function companyInfo(): array
    {
        $b = PlatformBranding::defaults();

        return [
            'company' => $b['company_name'],
            'address' => $b['address'],
            'phone' => $b['phone'],
            'email' => $b['email'],
            'website' => $b['website'],
            'logo_url' => $b['logo_url'],
            'branding' => $b,
            'currency' => tenancy()->tenant?->currency ?? 'ZMW',
            'invoice_footer' => $b['invoice_footer'],
        ];
    }

    public function loanApplication(Loan $loan): Response
    {
        $loan->load([
            'borrower',
            'loanType:id,name',
            'loanPlan:id,name',
            'createdBy:id,name',
        ]);

        $borrower = $loan->borrower;

        $loanData = [
            'loan_number' => $loan->loan_number,
            'loan_type' => $loan->loanType->name,
            'loan_plan' => $loan->loanPlan->name,
            'currency' => tenancy()->tenant?->currency ?? 'ZMW',
            'principal_amount' => number_format((float) $loan->principal_amount, 2),
            'tenure' => $loan->tenure,
            'tenure_type' => $loan->tenure_type,
            'repayment_schedule' => $loan->repayment_schedule,
            'application_date' => $loan->application_date?->format('d M Y'),
            'loan_purpose' => $loan->loan_purpose,
            'collateral_description' => $loan->collateral_description,
            'guarantor_name' => $loan->guarantor_name,
            'guarantor_phone' => $loan->guarantor_phone,
            'guarantor_relationship' => $loan->guarantor_relationship,
            'created_by' => $loan->createdBy?->name,
            'borrower' => [
                'name' => $borrower->full_name,
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
            ],
        ];

        $info = $this->companyInfo();

        $pdf = Pdf::loadView('pdf.loan-application', [
            'loan' => $loanData,
            'company' => $info['company'],
            'address' => $info['address'],
            'phone' => $info['phone'],
            'email' => $info['email'],
            'website' => $info['website'],
            'logo_url' => $info['logo_url'],
            'invoice_footer' => $info['invoice_footer'],
            'generatedAt' => now()->format('d M Y H:i'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("loan-application-{$loan->loan_number}.pdf");
    }

    public function loanAgreement(Loan $loan): Response
    {
        $loan->load([
            'borrower:id,first_name,last_name,borrower_number,phone,city',
            'loanType:id,name',
            'loanPlan:id,name',
            'approvedBy:id,name',
            'schedule',
        ]);

        $schedule = $loan->schedule->map(fn ($s) => [
            'instalment_number' => $s->instalment_number,
            'due_date' => $s->due_date->format('d M Y'),
            'total_due' => number_format((float) $s->total_due, 2),
            'total_paid' => number_format((float) $s->total_paid, 2),
            'outstanding' => number_format((float) $s->outstanding, 2),
            'is_paid' => $s->is_paid,
            'days_overdue' => $s->days_overdue,
        ])->toArray();

        $loanData = [
            'loan_number' => $loan->loan_number,
            'loan_type' => $loan->loanType->name,
            'loan_plan' => $loan->loanPlan->name,
            'currency' => tenancy()->tenant?->currency ?? 'ZMW',
            'principal_amount' => number_format((float) $loan->principal_amount, 2),
            'interest_amount' => number_format((float) $loan->interest_amount, 2),
            'processing_fee' => number_format((float) $loan->processing_fee, 2),
            'insurance_fee' => number_format((float) $loan->insurance_fee, 2),
            'total_payable' => number_format((float) $loan->total_payable, 2),
            'interest_rate' => $loan->interest_rate,
            'interest_type' => $loan->interest_type,
            'tenure' => $loan->tenure,
            'tenure_type' => $loan->tenure_type,
            'repayment_schedule' => $loan->repayment_schedule,
            'application_date' => $loan->application_date?->format('d M Y'),
            'approval_date' => $loan->approval_date?->format('d M Y'),
            'disbursement_date' => $loan->disbursement_date?->format('d M Y'),
            'first_repayment_date' => $loan->first_repayment_date?->format('d M Y'),
            'maturity_date' => $loan->maturity_date?->format('d M Y'),
            'loan_purpose' => $loan->loan_purpose,
            'collateral_description' => $loan->collateral_description,
            'guarantor_name' => $loan->guarantor_name,
            'guarantor_phone' => $loan->guarantor_phone,
            'guarantor_relationship' => $loan->guarantor_relationship,
            'approved_by' => $loan->approvedBy?->name,
            'borrower' => [
                'name' => $loan->borrower->full_name,
                'borrower_number' => $loan->borrower->borrower_number,
                'phone' => $loan->borrower->phone,
                'city' => $loan->borrower->city,
            ],
            'schedule' => $schedule,
        ];

        $info = $this->companyInfo();

        $pdf = Pdf::loadView('pdf.loan-agreement', [
            'loan' => $loanData,
            'company' => $info['company'],
            'address' => $info['address'],
            'phone' => $info['phone'],
            'email' => $info['email'],
            'website' => $info['website'],
            'logo_url' => $info['logo_url'],
            'invoice_footer' => $info['invoice_footer'],
            'generatedAt' => now()->format('d M Y H:i'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("loan-agreement-{$loan->loan_number}.pdf");
    }

    public function paymentReceipt(Payment $payment): Response
    {
        $payment->load(['loan.borrower', 'loan.loanType:id,name', 'recordedBy:id,name']);
        $info = $this->companyInfo();

        $paymentData = [
            'receipt_number' => $payment->receipt_number,
            'loan_number' => $payment->loan->loan_number,
            'borrower_name' => $payment->loan->borrower->full_name,
            'borrower_number' => $payment->loan->borrower->borrower_number,
            'loan_type' => $payment->loan->loanType->name,
            'amount' => number_format((float) $payment->amount, 2),
            'principal_allocated' => number_format((float) $payment->principal_allocated, 2),
            'interest_allocated' => number_format((float) $payment->interest_allocated, 2),
            'penalty_allocated' => number_format((float) $payment->penalty_allocated, 2),
            'payment_method' => $payment->payment_method->label(),
            'payment_date' => $payment->payment_date->format('d M Y'),
            'reference' => $payment->reference,
            'notes' => $payment->notes,
            'recorded_by' => $payment->recordedBy?->name,
        ];

        $pdf = Pdf::loadView('pdf.payment-receipt', [
            'payment' => $paymentData,
            'company' => $info['company'],
            'address' => $info['address'],
            'phone' => $info['phone'],
            'email' => $info['email'],
            'website' => $info['website'],
            'logo_url' => $info['logo_url'],
            'invoice_footer' => $info['invoice_footer'],
            'currency' => $info['currency'],
            'generatedAt' => now()->format('d M Y H:i'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("receipt-{$payment->receipt_number}.pdf");
    }

    public function repaymentSchedule(Loan $loan): Response
    {
        $loan->load([
            'borrower:id,first_name,last_name,borrower_number',
            'loanType:id,name',
            'loanPlan:id,name',
            'schedule',
        ]);
        $info = $this->companyInfo();

        $schedule = $loan->schedule->map(fn ($s) => [
            'instalment_number' => $s->instalment_number,
            'due_date' => $s->due_date->format('d M Y'),
            'total_due' => number_format((float) $s->total_due, 2),
            'total_paid' => number_format((float) $s->total_paid, 2),
            'outstanding' => number_format((float) $s->outstanding, 2),
            'is_paid' => $s->is_paid,
            'days_overdue' => $s->days_overdue,
        ])->toArray();

        $loanData = [
            'loan_number' => $loan->loan_number,
            'loan_type' => $loan->loanType->name,
            'loan_plan' => $loan->loanPlan->name,
            'status_label' => $loan->status->label(),
            'currency' => tenancy()->tenant?->currency ?? 'ZMW',
            'principal_amount' => number_format((float) $loan->principal_amount, 2),
            'total_payable' => number_format((float) $loan->total_payable, 2),
            'total_paid' => number_format((float) $loan->total_paid, 2),
            'outstanding_balance' => number_format((float) $loan->outstanding_balance, 2),
            'penalty_balance' => number_format((float) $loan->penalty_balance, 2),
            'interest_rate' => $loan->interest_rate,
            'interest_type' => $loan->interest_type,
            'repayment_schedule' => $loan->repayment_schedule,
            'tenure' => $loan->tenure,
            'tenure_type' => $loan->tenure_type,
            'first_repayment_date' => $loan->first_repayment_date?->format('d M Y'),
            'maturity_date' => $loan->maturity_date?->format('d M Y'),
            'borrower' => [
                'name' => $loan->borrower->full_name,
                'borrower_number' => $loan->borrower->borrower_number,
            ],
            'schedule' => $schedule,
        ];

        $pdf = Pdf::loadView('pdf.repayment-schedule', [
            'loan' => $loanData,
            'company' => $info['company'],
            'address' => $info['address'],
            'phone' => $info['phone'],
            'email' => $info['email'],
            'logo_url' => $info['logo_url'],
            'invoice_footer' => $info['invoice_footer'],
            'currency' => $info['currency'],
            'generatedAt' => now()->format('d M Y H:i'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("schedule-{$loan->loan_number}.pdf");
    }

    public function disbursementLetter(Loan $loan): Response
    {
        $loan->load([
            'borrower:id,first_name,last_name,borrower_number,phone,email,city',
            'loanType:id,name',
            'loanPlan:id,name',
            'disbursedBy:id,name',
        ]);
        $info = $this->companyInfo();

        $loanData = [
            'loan_number' => $loan->loan_number,
            'loan_type' => $loan->loanType->name,
            'currency' => tenancy()->tenant?->currency ?? 'ZMW',
            'principal_amount' => number_format((float) $loan->principal_amount, 2),
            'disbursement_method' => $loan->disbursement_method?->label(),
            'disbursement_account' => $loan->disbursement_account,
            'disbursement_date' => $loan->disbursement_date?->format('d M Y'),
            'first_repayment_date' => $loan->first_repayment_date?->format('d M Y'),
            'maturity_date' => $loan->maturity_date?->format('d M Y'),
            'interest_rate' => $loan->interest_rate,
            'interest_type' => $loan->interest_type,
            'tenure' => $loan->tenure,
            'tenure_type' => $loan->tenure_type,
            'loan_purpose' => $loan->loan_purpose,
            'disbursed_by' => $loan->disbursedBy?->name,
            'borrower' => [
                'name' => $loan->borrower->full_name,
                'borrower_number' => $loan->borrower->borrower_number,
                'phone' => $loan->borrower->phone,
                'email' => $loan->borrower->email,
                'city' => $loan->borrower->city,
            ],
        ];

        $pdf = Pdf::loadView('pdf.disbursement-letter', [
            'loan' => $loanData,
            'company' => $info['company'],
            'address' => $info['address'],
            'phone' => $info['phone'],
            'email' => $info['email'],
            'website' => $info['website'],
            'logo_url' => $info['logo_url'],
            'invoice_footer' => $info['invoice_footer'],
            'generatedAt' => now()->format('d M Y H:i'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("disbursement-{$loan->loan_number}.pdf");
    }

    public function accountStatement(Borrower $borrower): Response
    {
        $borrower->load([
            'loans' => fn ($q) => $q->with(['loanType:id,name', 'payments.recordedBy:id,name']),
        ]);
        $info = $this->companyInfo();

        $totalDisbursed = 0;
        $totalPaid = 0;
        $outstanding = 0;

        $loans = $borrower->loans->map(function ($loan) use (&$totalDisbursed, &$totalPaid, &$outstanding, $info) {
            $totalDisbursed += (float) $loan->principal_amount;
            $totalPaid += (float) $loan->total_paid;
            $outstanding += (float) $loan->outstanding_balance;

            $payments = $loan->payments->map(fn ($p) => [
                'receipt_number' => $p->receipt_number,
                'payment_date' => $p->payment_date->format('d M Y'),
                'payment_method' => $p->payment_method->label(),
                'amount' => number_format((float) $p->amount, 2),
                'principal_allocated' => number_format((float) $p->principal_allocated, 2),
                'interest_allocated' => number_format((float) $p->interest_allocated, 2),
            ])->toArray();

            return [
                'loan_number' => $loan->loan_number,
                'loan_type' => $loan->loanType->name,
                'status' => $loan->status->value,
                'status_label' => $loan->status->label(),
                'currency' => $info['currency'],
                'principal_amount' => number_format((float) $loan->principal_amount, 2),
                'total_payable' => number_format((float) $loan->total_payable, 2),
                'total_paid' => number_format((float) $loan->total_paid, 2),
                'outstanding_balance' => number_format((float) $loan->outstanding_balance, 2),
                'application_date' => $loan->application_date?->format('d M Y'),
                'disbursement_date' => $loan->disbursement_date?->format('d M Y'),
                'maturity_date' => $loan->maturity_date?->format('d M Y'),
                'payments' => $payments,
            ];
        })->toArray();

        $pdf = Pdf::loadView('pdf.account-statement', [
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
                'total_disbursed' => $totalDisbursed,
                'total_paid' => $totalPaid,
                'outstanding' => $outstanding,
            ],
            'company' => $info['company'],
            'address' => $info['address'],
            'phone' => $info['phone'],
            'email' => $info['email'],
            'logo_url' => $info['logo_url'],
            'invoice_footer' => $info['invoice_footer'],
            'currency' => $info['currency'],
            'generatedAt' => now()->format('d M Y H:i'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("statement-{$borrower->borrower_number}.pdf");
    }
}
