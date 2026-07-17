<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\ExchangeRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeRateController extends BaseApiController
{
    /**
     * GET /api/v1/exchange-rates
     * List rates, optionally filtered by from/to currency pair or effective date.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ExchangeRate::query()->orderByDesc('effective_date');

        if ($request->filled('from')) {
            $query->where('from_currency', strtoupper($request->from));
        }
        if ($request->filled('to')) {
            $query->where('to_currency', strtoupper($request->to));
        }
        if ($request->filled('date')) {
            $query->where('effective_date', $request->date);
        }

        $rates = $query->paginate($request->integer('per_page', 20));

        return $this->success([
            'data'       => $rates->items(),
            'pagination' => [
                'total'        => $rates->total(),
                'per_page'     => $rates->perPage(),
                'current_page' => $rates->currentPage(),
                'last_page'    => $rates->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/exchange-rates
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_currency'  => ['required', 'string', 'size:3'],
            'to_currency'    => ['required', 'string', 'size:3'],
            'rate'           => ['required', 'numeric', 'min:0.000001'],
            'effective_date' => ['required', 'date'],
        ]);

        $data['from_currency'] = strtoupper($data['from_currency']);
        $data['to_currency']   = strtoupper($data['to_currency']);

        $rate = ExchangeRate::updateOrCreate(
            [
                'from_currency'  => $data['from_currency'],
                'to_currency'    => $data['to_currency'],
                'effective_date' => $data['effective_date'],
            ],
            ['rate' => $data['rate']]
        );

        return $this->success($rate, 'Exchange rate saved.', 201);
    }

    /**
     * PUT /api/v1/exchange-rates/{exchangeRate}
     */
    public function update(Request $request, ExchangeRate $exchangeRate): JsonResponse
    {
        $data = $request->validate([
            'rate'           => ['sometimes', 'numeric', 'min:0.000001'],
            'effective_date' => ['sometimes', 'date'],
        ]);

        $exchangeRate->update($data);

        return $this->success($exchangeRate, 'Exchange rate updated.');
    }

    /**
     * DELETE /api/v1/exchange-rates/{exchangeRate}
     */
    public function destroy(ExchangeRate $exchangeRate): JsonResponse
    {
        $exchangeRate->delete();

        return $this->success(null, 'Exchange rate deleted.');
    }

    /**
     * GET /api/v1/exchange-rates/current?from=USD&to=ZMW
     * Returns the most recent rate for a given pair as of today.
     */
    public function current(Request $request): JsonResponse
    {
        $from = strtoupper($request->string('from', 'USD'));
        $to   = strtoupper($request->string('to', 'ZMW'));

        $rate = ExchangeRate::current($from, $to);

        if (! $rate) {
            return $this->error("No exchange rate found for {$from}/{$to}.", 404);
        }

        return $this->success([
            'from_currency'  => $rate->from_currency,
            'to_currency'    => $rate->to_currency,
            'rate'           => (float) $rate->rate,
            'effective_date' => $rate->effective_date->toDateString(),
            'updated_at'     => $rate->updated_at->toDateTimeString(),
        ]);
    }
}
