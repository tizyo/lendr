<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\SavingsAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavingsAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $query = SavingsAccount::with('borrower:id,first_name,last_name,borrower_number,phone')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->search, fn ($q, $s) => $q->whereHas('borrower', fn ($bq) => $bq->where('first_name', 'like', "%{$s}%")
                ->orWhere('last_name', 'like', "%{$s}%")
                ->orWhere('borrower_number', 'like', "%{$s}%"),
            ))
            ->orderByDesc('id');

        return Inertia::render('savings/Index', [
            'accounts' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function show(SavingsAccount $savings): Response
    {
        $savings->load(['borrower', 'transactions' => fn ($q) => $q->orderByDesc('id')->limit(50)]);

        return Inertia::render('savings/Show', [
            'account' => $savings,
        ]);
    }
}
