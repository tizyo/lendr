<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\FinancialStatementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialStatementController extends BaseApiController
{
    public function __construct(private FinancialStatementService $service) {}

    public function balanceSheet(Request $request): JsonResponse
    {
        $request->validate(['as_of' => ['nullable', 'date']]);

        return $this->success(
            $this->service->balanceSheet($request->as_of),
            'Balance sheet generated.',
        );
    }

    public function incomeStatement(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return $this->success(
            $this->service->incomeStatement($request->from, $request->to),
            'Income statement generated.',
        );
    }

    public function cashFlow(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return $this->success(
            $this->service->cashFlow($request->from, $request->to),
            'Cash flow statement generated.',
        );
    }

    public function portfolioAtRisk(Request $request): JsonResponse
    {
        $request->validate(['as_of' => ['nullable', 'date']]);

        return $this->success(
            $this->service->portfolioAtRisk($request->as_of),
            'Portfolio at risk calculated.',
        );
    }
}
