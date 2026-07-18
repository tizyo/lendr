<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ExchangeRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeRateController extends Controller
{
    public function index(Request $request): Response
    {
        $rates = ExchangeRate::query()
            ->when($request->from, fn ($q, $v) => $q->where('from_currency', strtoupper($v)))
            ->when($request->to, fn ($q, $v) => $q->where('to_currency', strtoupper($v)))
            ->orderByDesc('effective_date')
            ->orderBy('from_currency')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('exchange-rates/Index', [
            'rates' => $rates,
            'filters' => $request->only('from', 'to'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_currency' => ['required', 'string', 'size:3'],
            'to_currency' => ['required', 'string', 'size:3'],
            'rate' => ['required', 'numeric', 'min:0.000001'],
            'effective_date' => ['required', 'date'],
        ]);

        $data['from_currency'] = strtoupper($data['from_currency']);
        $data['to_currency'] = strtoupper($data['to_currency']);

        ExchangeRate::updateOrCreate(
            [
                'from_currency' => $data['from_currency'],
                'to_currency' => $data['to_currency'],
                'effective_date' => $data['effective_date'],
            ],
            ['rate' => $data['rate']],
        );

        return back()->with('success', 'Exchange rate saved.');
    }

    public function update(Request $request, ExchangeRate $exchangeRate): RedirectResponse
    {
        $data = $request->validate([
            'rate' => ['required', 'numeric', 'min:0.000001'],
            'effective_date' => ['required', 'date'],
        ]);

        $exchangeRate->update($data);

        return back()->with('success', 'Exchange rate updated.');
    }

    public function destroy(ExchangeRate $exchangeRate): RedirectResponse
    {
        $exchangeRate->delete();

        return back()->with('success', 'Exchange rate deleted.');
    }
}
