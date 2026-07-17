<?php

namespace App\Services;

use App\Models\Tenant\AgreementAuditEvent;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanAgreement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ESignatureService
{
    private const OTP_TTL_MINUTES  = 15;
    private const MAX_OTP_ATTEMPTS = 5;

    /**
     * Generate the loan agreement PDF and create/reset the LoanAgreement record.
     */
    public function generate(Loan $loan, ?int $generatedBy, string $ip = '', string $ua = ''): LoanAgreement
    {
        $loan->load(['borrower', 'loanPlan', 'loanType']);

        $pdfData  = $this->renderPdf($loan);
        $hash     = hash('sha256', $pdfData);
        $filename = 'agreements/loan_' . $loan->id . '_' . now()->format('Ymd_His') . '.pdf';

        Storage::disk('local')->put($filename, $pdfData);

        $agreement = LoanAgreement::updateOrCreate(
            ['loan_id' => $loan->id],
            [
                'document_hash' => $hash,
                'pdf_path'      => $filename,
                'status'        => 'pending',
                'otp_hash'      => null,
                'otp_expires_at' => null,
                'otp_attempts'  => 0,
                'signed_at'     => null,
                'generated_by'  => $generatedBy,
            ]
        );

        $this->audit($agreement, 'generated', auth()->user()?->name ?? 'system', $ip, $ua, [
            'document_hash' => $hash,
        ]);

        return $agreement->fresh();
    }

    /**
     * Generate and send a signing OTP to the borrower's phone.
     * Returns the plain OTP (for SMS dispatch; caller sends the SMS).
     */
    public function sendOtp(LoanAgreement $agreement, string $ip = '', string $ua = ''): string
    {
        $otp = (string) random_int(100000, 999999);

        $agreement->update([
            'status'          => 'otp_sent',
            'otp_hash'        => Hash::make($otp),
            'otp_expires_at'  => now()->addMinutes(self::OTP_TTL_MINUTES),
            'otp_attempts'    => 0,
        ]);

        $borrower = $agreement->loan->borrower;
        $this->audit($agreement, 'otp_sent', $borrower->phone ?? 'unknown', $ip, $ua, [
            'phone' => $borrower->phone,
        ]);

        return $otp;
    }

    /**
     * Verify OTP and mark the agreement as signed.
     */
    public function sign(LoanAgreement $agreement, string $otp, string $ip = '', string $ua = ''): bool
    {
        $agreement->increment('otp_attempts');

        if ($agreement->otp_attempts > self::MAX_OTP_ATTEMPTS) {
            $this->audit($agreement, 'sign_failed', null, $ip, $ua, ['reason' => 'max_attempts_exceeded']);
            return false;
        }

        if (! $agreement->isOtpValid($otp)) {
            $this->audit($agreement, 'sign_failed', null, $ip, $ua, [
                'reason'   => 'invalid_otp',
                'attempts' => $agreement->otp_attempts,
            ]);
            return false;
        }

        $borrower = $agreement->loan->borrower;

        $agreement->update([
            'status'          => 'signed',
            'otp_hash'        => null,
            'signed_by_name'  => $borrower->first_name . ' ' . $borrower->last_name,
            'signed_by_phone' => $borrower->phone,
            'signing_ip'      => $ip,
            'signing_device'  => $ua,
            'signed_at'       => now(),
        ]);

        $this->audit($agreement, 'signed', $borrower->phone, $ip, $ua, [
            'document_hash' => $agreement->document_hash,
        ]);

        return true;
    }

    /**
     * Void an agreement.
     */
    public function void(LoanAgreement $agreement, string $reason = '', string $ip = '', string $ua = ''): void
    {
        $agreement->update(['status' => 'voided']);
        $this->audit($agreement, 'voided', auth()->user()?->name ?? 'system', $ip, $ua, [
            'reason' => $reason,
        ]);
    }

    /**
     * Log a download event.
     */
    public function logDownload(LoanAgreement $agreement, string $ip = '', string $ua = ''): void
    {
        $this->audit($agreement, 'downloaded', auth()->user()?->name ?? 'system', $ip, $ua);
    }

    /**
     * Return raw PDF bytes for an agreement.
     */
    public function getPdfBytes(LoanAgreement $agreement): ?string
    {
        if (! $agreement->pdf_path) {
            return null;
        }
        return Storage::disk('local')->get($agreement->pdf_path);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function renderPdf(Loan $loan): string
    {
        $borrower = $loan->borrower;
        $b = \App\Models\Landlord\PlatformBranding::defaults();

        $loanData = [
            'loan_number'          => $loan->loan_number,
            'loan_type'            => $loan->loanType?->name ?? '—',
            'loan_plan'            => $loan->loanPlan?->name ?? '—',
            'currency'             => tenancy()->tenant?->currency ?? 'ZMW',
            'principal_amount'     => number_format((float) $loan->principal_amount, 2),
            'interest_amount'      => number_format((float) $loan->interest_amount, 2),
            'processing_fee'       => number_format((float) $loan->processing_fee, 2),
            'insurance_fee'        => number_format((float) $loan->insurance_fee, 2),
            'total_payable'        => number_format((float) $loan->total_payable, 2),
            'interest_rate'        => $loan->interest_rate,
            'interest_type'        => $loan->interest_type,
            'tenure'               => $loan->tenure,
            'tenure_type'          => $loan->tenure_type,
            'repayment_schedule'   => $loan->repayment_schedule,
            'application_date'     => $loan->application_date?->format('d M Y'),
            'approval_date'        => $loan->approval_date?->format('d M Y'),
            'disbursement_date'    => $loan->disbursement_date?->format('d M Y'),
            'first_repayment_date' => $loan->first_repayment_date?->format('d M Y'),
            'maturity_date'        => $loan->maturity_date?->format('d M Y'),
            'loan_purpose'         => $loan->loan_purpose,
            'collateral_description' => $loan->collateral_description,
            'guarantor_name'       => $loan->guarantor_name,
            'guarantor_phone'      => $loan->guarantor_phone,
            'guarantor_relationship' => $loan->guarantor_relationship,
            'approved_by'          => null,
            'borrower' => [
                'name'            => $borrower ? ($borrower->first_name . ' ' . $borrower->last_name) : '—',
                'borrower_number' => $borrower?->borrower_number ?? '—',
                'phone'           => $borrower?->phone ?? '—',
                'city'            => $borrower?->city ?? '—',
            ],
            'schedule' => [],
        ];

        $pdf = Pdf::loadView('pdf.loan-agreement', [
            'loan'           => $loanData,
            'company'        => $b['company_name'] ?? 'Lendr',
            'address'        => $b['address']      ?? '',
            'phone'          => $b['phone']         ?? '',
            'email'          => $b['email']         ?? '',
            'website'        => $b['website']       ?? '',
            'logo_url'       => $b['logo_url']      ?? null,
            'invoice_footer' => $b['invoice_footer'] ?? '',
            'generatedAt'    => now()->format('d M Y H:i'),
        ])->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    private function audit(
        LoanAgreement $agreement,
        string $event,
        ?string $actor,
        string $ip = '',
        string $ua = '',
        array $context = []
    ): void {
        AgreementAuditEvent::create([
            'loan_agreement_id' => $agreement->id,
            'event'             => $event,
            'actor'             => $actor,
            'ip_address'        => $ip ?: null,
            'user_agent'        => $ua  ?: null,
            'context'           => $context ?: null,
            'occurred_at'       => now(),
        ]);
    }
}
