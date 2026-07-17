<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\LoanType;
use Inertia\Inertia;
use Inertia\Response;

class LoanTypeController extends Controller
{
    public function index(): Response
    {
        $types = LoanType::with(['plans' => fn ($q) => $q->orderBy('name')])
            ->withCount('plans')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'id'                  => $t->id,
                'name'                => $t->name,
                'code'                => $t->code,
                'description'         => $t->description,
                'requires_collateral' => $t->requires_collateral,
                'requires_guarantor'  => $t->requires_guarantor,
                'required_documents'  => $t->required_documents ?? [],
                'is_active'           => $t->is_active,
                'sort_order'          => $t->sort_order,
                'plans_count'         => $t->plans_count,
                'plans'               => $t->plans->map(fn ($p) => [
                    'id'                 => $p->id,
                    'name'               => $p->name,
                    'code'               => $p->code,
                    'interest_rate'      => (float) $p->interest_rate,
                    'interest_type'      => $p->interest_type,
                    'interest_period'    => $p->interest_period,
                    'min_tenure'         => $p->min_tenure,
                    'max_tenure'         => $p->max_tenure,
                    'tenure_type'        => $p->tenure_type,
                    'min_amount'         => (float) $p->min_amount,
                    'max_amount'         => (float) $p->max_amount,
                    'penalty_rate'       => (float) $p->penalty_rate,
                    'penalty_type'       => $p->penalty_type,
                    'grace_period_days'  => $p->grace_period_days,
                    'repayment_schedule' => $p->repayment_schedule,
                    'processing_fee'     => (float) $p->processing_fee,
                    'insurance_fee'      => (float) $p->insurance_fee,
                    'is_active'          => $p->is_active,
                ])->values()->all(),
            ]);

        return Inertia::render('loan-types/Index', [
            'types' => $types,
        ]);
    }
}
