<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Borrower;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AppController extends Controller
{
    // ─── Auth Pages (guest) ───────────────────────────────────

    public function login(): Response
    {
        return Inertia::render('auth/Login');
    }

    public function showOtp(Request $request): Response
    {
        return Inertia::render('auth/VerifyOtp', [
            'phone' => $request->query('phone', ''),
        ]);
    }

    public function showSetPin(): Response
    {
        return Inertia::render('auth/SetPin');
    }

    // ─── Authenticated Pages ──────────────────────────────────

    public function dashboard(Request $request): Response
    {
        return Inertia::render('Dashboard');
    }

    public function loans(Request $request): Response
    {
        return Inertia::render('Loans');
    }

    public function payments(Request $request): Response
    {
        return Inertia::render('Payments');
    }

    public function notifications(): Response
    {
        return Inertia::render('Notifications');
    }

    public function profile(Request $request): Response
    {
        return Inertia::render('Profile');
    }

    // ─── Loan Pages ───────────────────────────────────────────

    public function loanApply(): Response
    {
        return Inertia::render('loans/Apply');
    }

    public function loanShow(int $id): Response
    {
        return Inertia::render('loans/Show', ['loanId' => $id]);
    }

    public function loanPay(int $id): Response
    {
        return Inertia::render('loans/Pay', ['loanId' => $id]);
    }

    // ─── Marketplace Pages ────────────────────────────────────

    public function marketplaceListings(): Response
    {
        return Inertia::render('marketplace/Listings');
    }

    public function marketplaceCreate(): Response
    {
        return Inertia::render('marketplace/Create');
    }

    public function publicProducts(): Response
    {
        return Inertia::render('marketplace/PublicProducts');
    }

    // ─── Repo Marketplace (ghost user) ────────────────────────

    public function repoBrowse(): Response
    {
        return Inertia::render('marketplace/RepoBrowse');
    }

    public function repoShow(int $id): Response
    {
        return Inertia::render('marketplace/RepoItemDetail', ['itemId' => $id]);
    }

    public function ghostLogin(): Response
    {
        return Inertia::render('marketplace/GhostLogin');
    }

    public function ghostVerify(): Response
    {
        return Inertia::render('marketplace/GhostVerify');
    }

    public function repoCart(): Response
    {
        return Inertia::render('marketplace/RepoCart');
    }

    public function repoMyEnquiries(): Response
    {
        return Inertia::render('marketplace/RepoEnquiries');
    }

    // ─── KYC Pages ────────────────────────────────────────────

    public function kycOnboarding(Request $request): Response
    {
        return Inertia::render('kyc/Onboarding');
    }

    public function kycStatus(Request $request): Response
    {
        /** @var Borrower|null $borrower */
        $borrower = Auth::guard('borrower')->user();

        $kycDocuments = [];
        $kycVerified = false;

        if ($borrower) {
            $kycDocuments = $borrower->kycDocuments()
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'document_type' => $d->document_type,
                    'status' => $d->status->value,
                    'status_label' => $d->status->label(),
                    'rejection_reason' => $d->rejection_reason,
                    'created_at' => $d->created_at->diffForHumans(),
                ])
                ->toArray();

            $kycVerified = (bool) $borrower->kyc_verified;
        }

        return Inertia::render('kyc/Status', [
            'kycDocuments' => $kycDocuments,
            'kycVerified' => $kycVerified,
        ]);
    }
}
