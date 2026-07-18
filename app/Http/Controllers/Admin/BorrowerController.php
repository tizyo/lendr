<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBorrowerRequest;
use App\Http\Requests\Admin\UpdateBorrowerRequest;
use App\Models\Tenant\Borrower;
use App\Services\PlanFeatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BorrowerController extends Controller
{
    public function index(Request $request): Response
    {
        $borrowers = Borrower::query()
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('first_name', 'like', "%{$s}%")
                    ->orWhere('last_name', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('borrower_number', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%");
            }))
            ->when($request->status, function ($q, $status) {
                match ($status) {
                    'active' => $q->where('is_active', true),
                    'inactive' => $q->where('is_active', false),
                    'blacklisted' => $q->where('is_blacklisted', true),
                    'kyc_verified' => $q->where('kyc_verified', true),
                    default => null,
                };
            })
            ->withCount(['loans', 'activeLoans'])
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($b) => [
                'id' => $b->id,
                'borrower_number' => $b->borrower_number,
                'full_name' => $b->full_name,
                'phone' => $b->phone,
                'email' => $b->email,
                'city' => $b->city,
                'is_active' => $b->is_active,
                'is_blacklisted' => $b->is_blacklisted,
                'kyc_verified' => $b->kyc_verified,
                'loans_count' => $b->loans_count,
                'active_loans_count' => $b->active_loans_count,
                'credit_score' => $b->credit_score,
                'verification_tier' => $b->verification_tier,
                'created_at' => $b->created_at->format('d M Y'),
            ]);

        return Inertia::render('borrowers/Index', [
            'borrowers' => $borrowers,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('borrowers/Create');
    }

    public function store(StoreBorrowerRequest $request): RedirectResponse
    {
        $svc = PlanFeatureService::forTenant();

        if (! $svc->canAddBorrower(Borrower::count())) {
            return redirect()->back()->with('error',
                "Your plan allows a maximum of {$svc->limitLabel('max_borrowers')} borrowers. Upgrade to add more.",
            );
        }

        $data = $request->validated();
        $data['borrower_number'] = $this->generateBorrowerNumber();

        $borrower = Borrower::create($data);

        return redirect()
            ->route('borrowers.show', $borrower)
            ->with('success', "Borrower {$borrower->full_name} created successfully.");
    }

    public function show(Borrower $borrower): Response
    {
        $borrower->load([
            'loans.loanType:id,name',
            'loans.loanPlan:id,name',
            'kycDocuments.reviewer:id,name',
        ]);

        $borrower->loadCount(['loans', 'activeLoans']);

        return Inertia::render('borrowers/Show', [
            'borrower' => [
                ...$borrower->toArray(),
                'full_name' => $borrower->full_name,
                'total_borrowed' => number_format((float) $borrower->total_borrowed, 2),
                'outstanding_balance' => number_format((float) $borrower->outstanding_balance, 2),
                'loans' => $borrower->loans->map(fn ($l) => [
                    'id' => $l->id,
                    'loan_number' => $l->loan_number,
                    'type' => $l->loanType->name,
                    'plan' => $l->loanPlan->name,
                    'amount' => number_format((float) $l->principal_amount, 2),
                    'status' => $l->status->value,
                    'status_label' => $l->status->label(),
                    'status_color' => $l->status->color(),
                    'date' => $l->application_date->format('d M Y'),
                ]),
                'kyc_documents' => $borrower->kycDocuments->map(fn ($d) => [
                    'id' => $d->id,
                    'document_type' => $d->document_type,
                    'status' => $d->status->value,
                    'status_label' => $d->status->label(),
                    'file_url' => $d->file_url,
                    'mime_type' => $d->mime_type,
                    'file_size' => $d->file_size,
                    'rejection_reason' => $d->rejection_reason,
                    'reviewed_by' => $d->reviewer?->name,
                    'expires_at' => $d->expires_at?->toDateString(),
                    'created_at' => $d->created_at->format('d M Y'),
                ]),
            ],
        ]);
    }

    public function edit(Borrower $borrower): Response
    {
        return Inertia::render('borrowers/Edit', ['borrower' => $borrower]);
    }

    public function update(UpdateBorrowerRequest $request, Borrower $borrower): RedirectResponse
    {
        $borrower->update($request->validated());

        return redirect()
            ->route('borrowers.show', $borrower)
            ->with('success', 'Borrower updated successfully.');
    }

    public function destroy(Borrower $borrower): RedirectResponse
    {
        // Prevent deletion if active loans exist
        if ($borrower->activeLoans()->exists()) {
            return back()->with('error', 'Cannot delete a borrower with active loans.');
        }

        $borrower->delete();

        return redirect()
            ->route('borrowers.index')
            ->with('success', 'Borrower deleted successfully.');
    }

    public function toggleBlacklist(Request $request, Borrower $borrower): RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $borrower->update([
            'is_blacklisted' => ! $borrower->is_blacklisted,
            'blacklist_reason' => $borrower->is_blacklisted ? null : $request->reason,
        ]);

        $action = $borrower->is_blacklisted ? 'blacklisted' : 'removed from blacklist';

        return back()->with('success', "Borrower {$action} successfully.");
    }

    private function generateBorrowerNumber(): string
    {
        $year = now()->year;
        $last = Borrower::withTrashed()
            ->where('borrower_number', 'like', "BOR-{$year}-%")
            ->max('borrower_number');

        $seq = $last
            ? ((int) Str::afterLast($last, '-')) + 1
            : 1;

        return "BOR-{$year}-".str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
