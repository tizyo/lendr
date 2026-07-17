<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ExpenseApprovalSetting;
use App\Models\Tenant\ExpenseBudget;
use App\Models\Tenant\ExpenseCategory;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseCategoryController extends Controller
{
    public function index(): Response
    {
        $categories = ExpenseCategory::query()
            ->withCount('expenses')
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id'             => $c->id,
                'name'           => $c->name,
                'code'           => $c->code,
                'icon'           => $c->icon,
                'colour'         => $c->colour,
                'description'    => $c->description,
                'is_active'      => $c->is_active,
                'expenses_count' => $c->expenses_count,
            ])
            ->values();

        $budgets = ExpenseBudget::query()
            ->with('category:id,name')
            ->where('period_year', now()->year)
            ->orderBy('period_month')
            ->get()
            ->map(fn ($b) => [
                'id'           => $b->id,
                'category_id'  => $b->expense_category_id,
                'category'     => $b->category?->name,
                'amount'       => (float) $b->amount,
                'period'       => $b->period,
                'period_year'  => $b->period_year,
                'period_month' => $b->period_month,
            ]);

        $approvalSettings = ExpenseApprovalSetting::query()
            ->with('category:id,name')
            ->get()
            ->map(fn ($s) => [
                'id'               => $s->id,
                'category_id'      => $s->expense_category_id,
                'category'         => $s->category?->name,
                'threshold_amount' => (float) $s->threshold_amount,
                'approver_role'    => $s->approver_role,
                'requires_receipt' => $s->requires_receipt,
            ]);

        return Inertia::render('expenses/Categories', compact('categories', 'budgets', 'approvalSettings'));
    }
}
