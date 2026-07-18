<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Borrower\StoreBorrowerRequest;
use App\Http\Requests\Api\V1\Borrower\UpdateBorrowerRequest;
use App\Models\Tenant\Borrower;
use App\Services\KycPullService;
use App\Services\PlanFeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class BorrowerController extends BaseApiController
{
    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Borrower::class);

        $borrowers = Borrower::query()
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('first_name', 'like', "%{$s}%")
                    ->orWhere('last_name', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('borrower_number', 'like', "%{$s}%")
                    ->orWhere('national_id', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%");
            }))
            ->when($request->status, function ($q, $status) {
                match ($status) {
                    'active' => $q->where('is_active', true)->where('is_blacklisted', false),
                    'inactive' => $q->where('is_active', false),
                    'blacklisted' => $q->where('is_blacklisted', true),
                    'kyc_verified' => $q->where('kyc_verified', true),
                    default => null,
                };
            })
            ->withCount(['loans', 'activeLoans'])
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($borrowers, fn ($b) => [
            'id' => $b->id,
            'borrower_number' => $b->borrower_number,
            'full_name' => $b->full_name,
            'first_name' => $b->first_name,
            'last_name' => $b->last_name,
            'phone' => $b->phone,
            'email' => $b->email,
            'city' => $b->city,
            'province' => $b->province,
            'is_active' => $b->is_active,
            'is_blacklisted' => $b->is_blacklisted,
            'kyc_verified' => $b->kyc_verified,
            'credit_score' => $b->credit_score,
            'verification_tier' => $b->verification_tier,
            'loans_count' => $b->loans_count,
            'active_loans_count' => $b->active_loans_count,
            'created_at' => $b->created_at->toDateString(),
        ]);
    }

    public function store(StoreBorrowerRequest $request): JsonResponse
    {
        $svc = PlanFeatureService::forTenant();
        if (! $svc->canAddBorrower(Borrower::count())) {
            return $this->error(
                'Your plan\'s borrower limit has been reached. Upgrade your plan to add more borrowers.',
                403,
            );
        }

        $data = $request->validated();
        $data['borrower_number'] = $this->generateBorrowerNumber();

        $borrower = Borrower::create($data);

        return $this->success($this->formatBorrower($borrower), 'Borrower created successfully.', 201);
    }

    public function show(Borrower $borrower): JsonResponse
    {
        $this->authorize('view', $borrower);

        $borrower->load(['kycDocuments.reviewer:id,name']);
        $borrower->loadCount(['loans', 'activeLoans']);

        return $this->success($this->formatBorrower($borrower, true), 'OK');
    }

    public function update(UpdateBorrowerRequest $request, Borrower $borrower): JsonResponse
    {
        $borrower->update($request->validated());

        return $this->success($this->formatBorrower($borrower->fresh()), 'Borrower updated successfully.');
    }

    public function destroy(Borrower $borrower): JsonResponse
    {
        $this->authorize('delete', $borrower);

        if ($borrower->activeLoans()->exists()) {
            return $this->error('Cannot delete a borrower with active loans.', 422);
        }

        $borrower->delete();

        return $this->success(null, 'Borrower deleted successfully.');
    }

    // ─── Loans ────────────────────────────────────────────────────────────────

    public function loans(Request $request, Borrower $borrower): JsonResponse
    {
        $loans = $borrower->loans()
            ->with('loanType:id,name', 'loanPlan:id,name')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($loans, fn ($l) => [
            'id' => $l->id,
            'loan_number' => $l->loan_number,
            'type' => $l->loanType?->name,
            'plan' => $l->loanPlan?->name,
            'principal' => (string) $l->principal_amount,
            'outstanding' => (string) $l->outstanding_balance,
            'status' => $l->status->value,
            'status_label' => $l->status->label(),
            'application_date' => $l->application_date?->toDateString(),
            'disbursed_at' => $l->disbursed_at?->toDateString(),
        ]);
    }

    // ─── Statement ────────────────────────────────────────────────────────────

    public function statement(Borrower $borrower): JsonResponse
    {
        $borrower->load(['loans.payments', 'loans.loanType:id,name']);
        $borrower->loadCount(['loans', 'activeLoans']);

        return $this->success([
            'borrower' => [
                'id' => $borrower->id,
                'borrower_number' => $borrower->borrower_number,
                'full_name' => $borrower->full_name,
                'phone' => $borrower->phone,
            ],
            'summary' => [
                'total_loans' => $borrower->loans_count,
                'active_loans' => $borrower->active_loans_count,
                'total_borrowed' => (string) $borrower->total_borrowed,
                'outstanding_balance' => (string) $borrower->outstanding_balance,
            ],
            'loans' => $borrower->loans->map(fn ($l) => [
                'id' => $l->id,
                'loan_number' => $l->loan_number,
                'type' => $l->loanType?->name,
                'principal' => (string) $l->principal_amount,
                'status' => $l->status->value,
                'payments' => $l->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'payment_number' => $p->payment_number,
                    'amount' => (string) $p->amount,
                    'method' => $p->method->value,
                    'paid_at' => $p->paid_at?->toDateString(),
                ]),
            ]),
            'generated_at' => now()->toIso8601String(),
        ], 'OK');
    }

    // ─── Notes ────────────────────────────────────────────────────────────────

    public function notes(Borrower $borrower): JsonResponse
    {
        $notes = Activity::forSubject($borrower)
            ->where('description', 'note_added')
            ->with('causer:id,name')
            ->latest()
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'note' => $a->properties->get('note'),
                'added_by' => $a->causer?->name,
                'created_at' => $a->created_at->toIso8601String(),
            ]);

        return $this->success($notes, 'OK');
    }

    public function addNote(Request $request, Borrower $borrower): JsonResponse
    {
        $request->validate(['note' => ['required', 'string', 'max:1000']]);

        activity()
            ->performedOn($borrower)
            ->causedBy($request->user())
            ->withProperties(['note' => $request->note])
            ->log('note_added');

        return $this->success(null, 'Note added successfully.', 201);
    }

    // ─── Blacklist ────────────────────────────────────────────────────────────

    public function toggleBlacklist(Request $request, Borrower $borrower): JsonResponse
    {
        $this->authorize('update', $borrower);

        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $nowBlacklisting = ! $borrower->is_blacklisted;

        $borrower->update([
            'is_blacklisted' => $nowBlacklisting,
            'blacklist_reason' => $nowBlacklisting ? $request->reason : null,
        ]);

        $action = $nowBlacklisting ? 'blacklisted' : 'removed from blacklist';

        return $this->success(
            ['is_blacklisted' => $borrower->is_blacklisted, 'blacklist_reason' => $borrower->blacklist_reason],
            "Borrower {$action} successfully.",
        );
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function formatBorrower(Borrower $b, bool $full = false): array
    {
        $data = [
            'id' => $b->id,
            'borrower_number' => $b->borrower_number,
            'full_name' => $b->full_name,
            'first_name' => $b->first_name,
            'last_name' => $b->last_name,
            'other_names' => $b->other_names,
            'email' => $b->email,
            'phone' => $b->phone,
            'phone_alt' => $b->phone_alt,
            'gender' => $b->gender,
            'date_of_birth' => $b->date_of_birth?->toDateString(),
            'national_id' => $b->national_id,
            'occupation' => $b->occupation,
            'employer' => $b->employer,
            'address' => $b->address,
            'city' => $b->city,
            'province' => $b->province,
            'country' => $b->country,
            'next_of_kin_name' => $b->next_of_kin_name,
            'next_of_kin_phone' => $b->next_of_kin_phone,
            'next_of_kin_relationship' => $b->next_of_kin_relationship,
            'avatar' => $b->avatar,
            'is_active' => $b->is_active,
            'is_blacklisted' => $b->is_blacklisted,
            'blacklist_reason' => $b->blacklist_reason,
            'kyc_verified' => $b->kyc_verified,
            'credit_score' => $b->credit_score,
            'verification_tier' => $b->verification_tier,
            'created_at' => $b->created_at->toIso8601String(),
        ];

        if ($full) {
            $data['loans_count'] = $b->loans_count ?? 0;
            $data['active_loans_count'] = $b->active_loans_count ?? 0;
            $data['total_borrowed'] = (string) $b->total_borrowed;
            $data['outstanding_balance'] = (string) $b->outstanding_balance;
            $data['kyc_documents'] = $b->relationLoaded('kycDocuments')
                ? $b->kycDocuments->map(fn ($d) => [
                    'id' => $d->id,
                    'document_type' => $d->document_type,
                    'file_url' => $d->file_url,
                    'mime_type' => $d->mime_type,
                    'file_size' => $d->file_size,
                    'status' => $d->status->value,
                    'status_label' => $d->status->label(),
                    'rejection_reason' => $d->rejection_reason,
                    'reviewed_by' => $d->reviewer?->name,
                    'reviewed_at' => $d->reviewed_at?->toIso8601String(),
                    'expires_at' => $d->expires_at?->toDateString(),
                    'created_at' => $d->created_at->toIso8601String(),
                ])
                : [];
        }

        return $data;
    }

    private function generateBorrowerNumber(): string
    {
        $prefix = 'BRW-'.now()->format('Ym').'-';
        $last = Borrower::withTrashed()
            ->where('borrower_number', 'like', "{$prefix}%")
            ->max('borrower_number');

        $seq = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return $prefix.str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    // ─── KYC Pull ─────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/borrowers/kyc-lookup
     * Look up a ghost user by national ID, TPIN, or company reg number.
     */
    public function kycLookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'national_id' => ['nullable', 'string'],
            'tpin_number' => ['nullable', 'string'],
            'company_reg_number' => ['nullable', 'string'],
        ]);

        if (empty(array_filter($data))) {
            return $this->error('At least one identifier (national_id, tpin_number, company_reg_number) is required.', 422);
        }

        $result = app(KycPullService::class)->lookup(
            $data['national_id'] ?? null,
            $data['tpin_number'] ?? null,
            $data['company_reg_number'] ?? null,
        );

        return $this->success($result);
    }

    /**
     * POST /api/v1/borrowers/{borrower}/kyc-import
     * Import selected fields from a ghost user into this borrower.
     */
    public function kycImport(Request $request, Borrower $borrower): JsonResponse
    {
        $data = $request->validate([
            'ghost_user_id' => ['required', 'integer'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string', 'in:name,phone,email,address,city,date_of_birth,gender,national_id,tpin_number,company_reg_number'],
        ]);

        $borrower = app(KycPullService::class)->import($borrower, $data['ghost_user_id'], $data['fields']);

        return $this->success(['borrower_id' => $borrower->id], 'KYC data imported successfully.');
    }
}
