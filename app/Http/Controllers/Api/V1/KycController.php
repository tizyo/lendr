<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\KycStatus;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\KycDocument;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class KycController extends BaseApiController
{
    public function __construct(private NotificationService $notifications) {}

    public function pending(Request $request): JsonResponse
    {
        $docs = KycDocument::with('borrower:id,borrower_number,first_name,last_name,phone')
            ->whereIn('status', [KycStatus::Pending->value, KycStatus::UnderReview->value])
            ->when($request->document_type, fn ($q, $t) => $q->where('document_type', $t))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($docs, fn ($d) => $this->formatDoc($d));
    }

    public function borrowerDocuments(Borrower $borrower): JsonResponse
    {
        $docs = $borrower->kycDocuments()->latest()->get()->map(fn ($d) => $this->formatDoc($d));

        $summary = [
            'kyc_verified' => $borrower->kyc_verified,
            'total' => $docs->count(),
            'pending' => $docs->where('status', 'pending')->count(),
            'under_review' => $docs->where('status', 'under_review')->count(),
            'verified' => $docs->where('status', 'verified')->count(),
            'rejected' => $docs->where('status', 'rejected')->count(),
            'expired' => $docs->where('status', 'expired')->count(),
        ];

        return $this->success(['summary' => $summary, 'documents' => $docs->values()]);
    }

    public function upload(Request $request, Borrower $borrower): JsonResponse
    {
        $request->validate([
            'document_type' => ['required', 'string', Rule::in([
                'national_id_front', 'national_id_back', 'passport',
                'drivers_licence', 'utility_bill', 'bank_statement', 'selfie', 'other',
            ])],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:10240'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $file = $request->file('file');
        $path = $file->storeAs(
            "kyc/{$borrower->id}",
            now()->format('YmdHis')."_{$request->document_type}.{$file->getClientOriginalExtension()}",
            'private',
        );

        $doc = KycDocument::create([
            'borrower_id' => $borrower->id,
            'document_type' => $request->document_type,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'status' => KycStatus::Pending,
            'expires_at' => $request->expires_at,
        ]);

        return $this->success($this->formatDoc($doc), 'Document uploaded successfully.', 201);
    }

    /** POST kyc/{document}/start-review — pending → under_review */
    public function startReview(KycDocument $document): JsonResponse
    {
        if (! $document->transitionTo(KycStatus::UnderReview)) {
            return $this->error("Cannot start review from status '{$document->status->value}'.", 422);
        }

        return $this->success($this->formatDoc($document->fresh()), 'Document moved to under review.');
    }

    /** PUT kyc/{document}/review — under_review → verified|rejected */
    public function review(Request $request, KycDocument $document): JsonResponse
    {
        $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'rejection_reason' => ['required_if:action,reject', 'nullable', 'string', 'max:500'],
        ]);

        $newStatus = $request->action === 'approve' ? KycStatus::Verified : KycStatus::Rejected;

        if (! $document->transitionTo($newStatus, [
            'rejection_reason' => $request->action === 'reject' ? $request->rejection_reason : null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])) {
            return $this->error("Cannot {$request->action} from status '{$document->status->value}'.", 422);
        }

        $document->refresh();

        if ($newStatus === KycStatus::Verified) {
            $this->syncBorrowerKycStatus($document->borrower);
        }

        return $this->success($this->formatDoc($document), 'Document '.$document->status->label().' successfully.');
    }

    public function borrowerUpload(Request $request): JsonResponse
    {
        $request->validate([
            'document_type' => ['required', 'string', Rule::in([
                'national_id_front', 'national_id_back', 'passport',
                'drivers_licence', 'utility_bill', 'bank_statement', 'selfie', 'other',
            ])],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:10240'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        /** @var \App\Models\Tenant\Borrower $borrower */
        $borrower = $request->user();
        $file = $request->file('file');
        $path = $file->storeAs(
            "kyc/{$borrower->id}",
            now()->format('YmdHis')."_{$request->document_type}.{$file->getClientOriginalExtension()}",
            'private',
        );

        $doc = KycDocument::create([
            'borrower_id' => $borrower->id,
            'document_type' => $request->document_type,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'status' => KycStatus::Pending,
            'expires_at' => $request->expires_at,
        ]);

        $this->notifications->notifyRoles(
            ['loan_officer', 'branch_manager', 'super_admin'],
            'kyc_submitted',
            "KYC document submitted: {$borrower->full_name}",
            ucwords(str_replace('_', ' ', $request->document_type)).' uploaded and awaiting review.',
            ['borrower_id' => $borrower->id, 'document_id' => $doc->id],
        );

        return $this->success($this->formatDoc($doc), 'Document uploaded. Pending review.', 201);
    }

    public function destroy(KycDocument $document): JsonResponse
    {
        if ($document->status === KycStatus::Verified) {
            return $this->error('Verified documents cannot be deleted.', 422);
        }

        Storage::disk('private')->delete($document->file_path);
        $document->delete();

        return $this->success(null, 'Document deleted successfully.');
    }

    public function view(KycDocument $document): JsonResponse
    {
        if (str_starts_with($document->file_path, 'http')) {
            return $this->success(['url' => $document->file_path], 'OK');
        }

        $url = Storage::disk('private')->temporaryUrl($document->file_path, now()->addMinutes(15));

        return $this->success(['url' => $url, 'expires_at' => now()->addMinutes(15)->toIso8601String()], 'OK');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function formatDoc(KycDocument $d): array
    {
        return [
            'id' => $d->id,
            'document_type' => $d->document_type,
            'file_url' => $d->file_url,
            'mime_type' => $d->mime_type,
            'file_size' => $d->file_size,
            'status' => $d->status->value,
            'status_label' => $d->status->label(),
            'rejection_reason' => $d->rejection_reason,
            'expires_at' => $d->expires_at?->toDateString(),
            'is_expired' => $d->isExpired(),
            'reviewed_by' => $d->reviewed_by,
            'reviewed_at' => $d->reviewed_at?->toIso8601String(),
            'uploaded_at' => $d->created_at->toIso8601String(),
            'borrower' => $d->relationLoaded('borrower') ? [
                'id' => $d->borrower->id,
                'borrower_number' => $d->borrower->borrower_number,
                'full_name' => $d->borrower->full_name,
                'phone' => $d->borrower->phone,
            ] : null,
        ];
    }

    private function syncBorrowerKycStatus(Borrower $borrower): void
    {
        $hasPending = $borrower->kycDocuments()
            ->whereIn('status', [KycStatus::Pending->value, KycStatus::UnderReview->value, KycStatus::Rejected->value])
            ->exists();

        $hasVerified = $borrower->kycDocuments()
            ->where('status', KycStatus::Verified->value)
            ->exists();

        if (! $hasPending && $hasVerified && ! $borrower->kyc_verified) {
            $borrower->update(['kyc_verified' => true]);
        }
    }
}
