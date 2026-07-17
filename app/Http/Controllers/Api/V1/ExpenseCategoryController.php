<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\ExpenseApprovalSetting;
use App\Models\Tenant\ExpenseBudget;
use App\Models\Tenant\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseCategoryController extends BaseApiController
{
    // ─── Categories ───────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $categories = ExpenseCategory::query()
            ->withCount('expenses')
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->active())
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => $this->formatCategory($c));

        return $this->success($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'code'        => ['required', 'string', 'alpha_dash', 'max:20', 'unique:expense_categories,code'],
            'icon'        => ['nullable', 'string', 'max:50'],
            'colour'      => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active'   => ['boolean'],
        ]);

        $category = ExpenseCategory::create($request->only(['name', 'code', 'icon', 'colour', 'description', 'is_active']));

        return $this->success($this->formatCategory($category), 'Category created.', 201);
    }

    public function show(ExpenseCategory $expenseCategory): JsonResponse
    {
        $expenseCategory->loadCount('expenses')->load('budgets');

        return $this->success($this->formatCategory($expenseCategory, true));
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $request->validate([
            'name'        => ['sometimes', 'string', 'max:100'],
            'code'        => ['sometimes', 'string', 'alpha_dash', 'max:20', 'unique:expense_categories,code,'.$expenseCategory->id],
            'icon'        => ['nullable', 'string', 'max:50'],
            'colour'      => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active'   => ['boolean'],
        ]);

        $expenseCategory->update($request->only(['name', 'code', 'icon', 'colour', 'description', 'is_active']));

        return $this->success($this->formatCategory($expenseCategory->fresh()), 'Category updated.');
    }

    public function destroy(ExpenseCategory $expenseCategory): JsonResponse
    {
        if ($expenseCategory->expenses()->exists()) {
            return $this->error('Cannot delete a category that has expenses.', 422);
        }

        $expenseCategory->delete();

        return $this->success(null, 'Category deleted.');
    }

    // ─── Budgets ──────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/expense-budgets
     * Budgets for current or given year, optionally by category.
     */
    public function budgets(Request $request): JsonResponse
    {
        $year = $request->integer('year', now()->year);

        $budgets = ExpenseBudget::query()
            ->with('category:id,name,code,colour')
            ->where('period_year', $year)
            ->when($request->category_id, fn ($q, $id) => $q->where('expense_category_id', $id))
            ->when($request->period, fn ($q, $p) => $q->where('period', $p))
            ->get()
            ->map(fn ($b) => [
                'id'          => $b->id,
                'category'    => ['id' => $b->category->id, 'name' => $b->category->name, 'colour' => $b->category->colour],
                'amount'      => (float) $b->amount,
                'period'      => $b->period,
                'period_year' => $b->period_year,
                'period_month' => $b->period_month,
                'notes'       => $b->notes,
            ]);

        return $this->success($budgets);
    }

    /**
     * POST /api/v1/expense-budgets
     * Create or update a budget for a category in a given period.
     */
    public function storeBudget(Request $request): JsonResponse
    {
        $request->validate([
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'amount'              => ['required', 'numeric', 'min:0'],
            'period'              => ['required', 'in:monthly,annual'],
            'period_year'         => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_month'        => ['nullable', 'integer', 'min:1', 'max:12'],
            'notes'               => ['nullable', 'string', 'max:500'],
        ]);

        $budget = ExpenseBudget::updateOrCreate(
            [
                'expense_category_id' => $request->expense_category_id,
                'period'              => $request->period,
                'period_year'         => $request->period_year,
                'period_month'        => $request->period === 'monthly' ? $request->period_month : null,
            ],
            [
                'amount' => $request->amount,
                'notes'  => $request->notes,
            ]
        );

        $budget->load('category:id,name,code,colour');

        return $this->success([
            'id'           => $budget->id,
            'category_id'  => $budget->expense_category_id,
            'category'     => $budget->category?->name,
            'amount'       => (float) $budget->amount,
            'period'       => $budget->period,
            'period_year'  => $budget->period_year,
            'period_month' => $budget->period_month,
        ], 'Budget saved.', 201);
    }

    // ─── Approval Settings ────────────────────────────────────────────────────

    /**
     * GET /api/v1/expense-settings
     * All approval settings (global + per-category).
     */
    public function settings(): JsonResponse
    {
        $settings = ExpenseApprovalSetting::query()
            ->with('category:id,name')
            ->get()
            ->map(fn ($s) => [
                'id'               => $s->id,
                'category'         => $s->category ? ['id' => $s->category->id, 'name' => $s->category->name] : null,
                'threshold_amount' => (float) $s->threshold_amount,
                'approver_role'    => $s->approver_role,
                'requires_receipt' => $s->requires_receipt,
            ]);

        return $this->success($settings);
    }

    /**
     * PUT /api/v1/expense-settings
     * Upsert approval settings for a category (or global with null category).
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'settings'                    => ['required', 'array'],
            'settings.*.category_id'      => ['nullable', 'exists:expense_categories,id'],
            'settings.*.threshold_amount' => ['required', 'numeric', 'min:0'],
            'settings.*.approver_role'    => ['required', 'string'],
            'settings.*.requires_receipt' => ['boolean'],
        ]);

        foreach ($request->settings as $item) {
            ExpenseApprovalSetting::updateOrCreate(
                ['expense_category_id' => $item['category_id'] ?? null],
                [
                    'threshold_amount' => $item['threshold_amount'],
                    'approver_role'    => $item['approver_role'],
                    'requires_receipt' => $item['requires_receipt'] ?? false,
                ]
            );
        }

        return $this->success(null, 'Settings updated.');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function formatCategory(ExpenseCategory $c, bool $full = false): array
    {
        $data = [
            'id'             => $c->id,
            'name'           => $c->name,
            'code'           => $c->code,
            'icon'           => $c->icon,
            'colour'         => $c->colour,
            'description'    => $c->description,
            'is_active'      => $c->is_active,
            'expenses_count' => $c->expenses_count ?? null,
        ];

        if ($full && $c->relationLoaded('budgets')) {
            $data['budgets'] = $c->budgets->map(fn ($b) => [
                'id'           => $b->id,
                'amount'       => (float) $b->amount,
                'period'       => $b->period,
                'period_year'  => $b->period_year,
                'period_month' => $b->period_month,
            ])->values();
        }

        return $data;
    }
}
