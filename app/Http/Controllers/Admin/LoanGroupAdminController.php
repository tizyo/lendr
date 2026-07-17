<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\LoanGroup;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoanGroupAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $query = LoanGroup::withCount(['activeMembers', 'loans'])
            ->with('officer:id,name')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderByDesc('id');

        return Inertia::render('loan-groups/Index', [
            'groups' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function show(LoanGroup $group): Response
    {
        $group->load(['officer', 'activeMembers.borrower', 'loans.borrower']);

        return Inertia::render('loan-groups/Show', [
            'group' => $group,
        ]);
    }
}
