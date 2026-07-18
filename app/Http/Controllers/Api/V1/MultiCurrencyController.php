<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Loan;
use App\Services\MultiCurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MultiCurrencyController extends BaseApiController
{
    public function __construct(private readonly MultiCurrencyService $fx) {}

    /** GET /api/v1/multi-currency/portfolio?base=ZMW */
    public function portfolio(Request $request): JsonResponse
    {
        $base = strtoupper($request->get('base', 'ZMW'));

        return $this->success($this->fx->portfolioSummary($base));
    }

    /** GET /api/v1/loans/{loan}/currency */
    public function loanInfo(Loan $loan): JsonResponse
    {
        return $this->success([
            'currency' => $loan->currency,
            'base_currency' => $loan->base_currency,
            'fx_rate' => (float) $loan->fx_rate,
            'outstanding' => (float) $loan->outstanding_balance,
            'outstanding_base' => $this->fx->outstandingInBase($loan),
        ]);
    }

    /** POST /api/v1/multi-currency/convert */
    public function convert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'from' => ['required', 'string', 'size:3'],
            'to' => ['required', 'string', 'size:3'],
        ]);

        $converted = $this->fx->convert(
            (float) $data['amount'],
            strtoupper($data['from']),
            strtoupper($data['to']),
        );

        $rate = $this->fx->rateFor(strtoupper($data['from']), strtoupper($data['to']));

        return $this->success([
            'from' => strtoupper($data['from']),
            'to' => strtoupper($data['to']),
            'amount' => (float) $data['amount'],
            'converted' => $converted,
            'rate' => $rate,
        ]);
    }
}
