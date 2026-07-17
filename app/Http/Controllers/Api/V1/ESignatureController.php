<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanAgreement;
use App\Services\ESignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ESignatureController extends BaseApiController
{
    public function __construct(private readonly ESignatureService $esig) {}

    /** GET /api/v1/loans/{loan}/agreement */
    public function show(Loan $loan): JsonResponse
    {
        $agreement = LoanAgreement::where('loan_id', $loan->id)->first();
        return $this->success($agreement ? $this->format($agreement) : null);
    }

    /** POST /api/v1/loans/{loan}/agreement/generate */
    public function generate(Request $request, Loan $loan): JsonResponse
    {
        $agreement = $this->esig->generate(
            $loan,
            auth()->id(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return $this->success($this->format($agreement), 'Agreement generated.', 201);
    }

    /** POST /api/v1/loans/{loan}/agreement/send-otp */
    public function sendOtp(Request $request, Loan $loan): JsonResponse
    {
        $agreement = LoanAgreement::where('loan_id', $loan->id)->firstOrFail();

        if ($agreement->isSigned()) {
            return $this->error('Agreement is already signed.', 422);
        }
        if ($agreement->status === 'voided') {
            return $this->error('Agreement has been voided.', 422);
        }
        if (! $agreement->pdf_path) {
            return $this->error('Generate the agreement PDF first.', 422);
        }

        $otp = $this->esig->sendOtp($agreement, $request->ip(), $request->userAgent() ?? '');

        // In production, dispatch SMS to borrower's phone.
        // Returned here for testing convenience (masked in non-debug).
        $masked = config('app.debug') ? $otp : null;

        return $this->success(['otp_sent' => true, 'otp' => $masked], 'OTP sent to borrower.');
    }

    /** POST /api/v1/loans/{loan}/agreement/sign */
    public function sign(Request $request, Loan $loan): JsonResponse
    {
        $request->validate(['otp' => ['required', 'string', 'size:6']]);

        $agreement = LoanAgreement::where('loan_id', $loan->id)->firstOrFail();

        if ($agreement->isSigned()) {
            return $this->error('Agreement is already signed.', 422);
        }
        if ($agreement->status === 'voided') {
            return $this->error('Agreement has been voided.', 422);
        }

        $ok = $this->esig->sign(
            $agreement,
            $request->otp,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        if (! $ok) {
            return $this->error('Invalid or expired OTP.', 422);
        }

        return $this->success($this->format($agreement->fresh()), 'Agreement signed successfully.');
    }

    /** GET /api/v1/loans/{loan}/agreement/audit */
    public function audit(Loan $loan): JsonResponse
    {
        $agreement = LoanAgreement::where('loan_id', $loan->id)->firstOrFail();

        $events = $agreement->auditEvents->map(fn ($e) => [
            'id'          => $e->id,
            'event'       => $e->event,
            'actor'       => $e->actor,
            'ip_address'  => $e->ip_address,
            'context'     => $e->context ?? [],
            'occurred_at' => $e->occurred_at?->toDateTimeString(),
        ]);

        return $this->success(['events' => $events]);
    }

    /** GET /api/v1/loans/{loan}/agreement/download */
    public function download(Request $request, Loan $loan): Response|JsonResponse
    {
        $agreement = LoanAgreement::where('loan_id', $loan->id)->firstOrFail();

        $bytes = $this->esig->getPdfBytes($agreement);
        if (! $bytes) {
            return $this->error('PDF not yet generated.', 404);
        }

        $this->esig->logDownload($agreement, $request->ip(), $request->userAgent() ?? '');

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="agreement_' . $loan->loan_number . '.pdf"',
        ]);
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function format(LoanAgreement $a): array
    {
        return [
            'id'             => $a->id,
            'loan_id'        => $a->loan_id,
            'status'         => $a->status,
            'document_hash'  => $a->document_hash,
            'has_pdf'        => (bool) $a->pdf_path,
            'signed_by_name' => $a->signed_by_name,
            'signed_by_phone' => $a->signed_by_phone,
            'signed_at'      => $a->signed_at?->toDateTimeString(),
            'generated_at'   => $a->created_at?->toDateTimeString(),
            'updated_at'     => $a->updated_at?->toDateTimeString(),
        ];
    }
}
