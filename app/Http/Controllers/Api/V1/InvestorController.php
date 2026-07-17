<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Investor;
use App\Models\Tenant\InvestorAllocation;
use App\Models\Tenant\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvestorController extends BaseApiController
{
    // ─── Investors CRUD ───────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $q = Investor::query();

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $q->where('type', $request->type);
        }
        if ($request->filled('search')) {
            $q->where(function ($sq) use ($request) {
                $sq->where('name', 'like', "%{$request->search}%")
                   ->orWhere('email', 'like', "%{$request->search}%")
                   ->orWhere('investor_number', 'like', "%{$request->search}%");
            });
        }

        $investors = $q->withCount('allocations')
            ->orderByDesc('id')
            ->paginate(20);

        return $this->success($investors->getCollection()->map(fn ($i) => $this->format($i))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'max:150', 'unique:investors,email'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'type'        => ['required', 'in:individual,institution'],
            'national_id' => ['nullable', 'string', 'max:60'],
            'address'     => ['nullable', 'string'],
            'country'     => ['nullable', 'string', 'max:60'],
            'notes'       => ['nullable', 'string'],
        ]);

        $investor = Investor::create(array_merge($data, [
            'investor_number' => Investor::generateInvestorNumber(),
        ]));

        return $this->success(['investor' => $this->format($investor)], 'Investor created.', 201);
    }

    public function show(Investor $investor): JsonResponse
    {
        $investor->loadCount('allocations');

        return $this->success(['investor' => $this->format($investor)]);
    }

    public function update(Request $request, Investor $investor): JsonResponse
    {
        $data = $request->validate([
            'name'    => ['sometimes', 'required', 'string', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:30'],
            'status'  => ['sometimes', 'in:active,suspended,exited'],
            'address' => ['nullable', 'string'],
            'notes'   => ['nullable', 'string'],
        ]);

        $investor->update($data);

        return $this->success(['investor' => $this->format($investor->fresh())]);
    }

    public function destroy(Investor $investor): JsonResponse
    {
        $investor->delete();

        return $this->success(null, 'Investor deleted.');
    }

    // ─── Allocations ──────────────────────────────────────────────────────────

    public function allocations(Investor $investor): JsonResponse
    {
        $allocations = $investor->allocations()
            ->with('loan:id,loan_number,principal_amount,status')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($a) => $this->formatAllocation($a));

        return $this->success($allocations);
    }

    public function allocate(Request $request, Investor $investor): JsonResponse
    {
        $data = $request->validate([
            'loan_id'          => ['required', 'exists:loans,id'],
            'allocated_amount' => ['required', 'numeric', 'min:1'],
            'expected_return'  => ['nullable', 'numeric', 'min:0'],
            'allocation_date'  => ['required', 'date'],
            'notes'            => ['nullable', 'string'],
        ]);

        $allocation = $investor->allocations()->create([
            'loan_id'          => $data['loan_id'],
            'recorded_by'      => $request->user()->id,
            'allocated_amount' => $data['allocated_amount'],
            'expected_return'  => $data['expected_return'] ?? 0,
            'actual_return'    => 0,
            'allocation_date'  => $data['allocation_date'],
            'notes'            => $data['notes'] ?? null,
            'status'           => 'active',
        ]);

        return $this->success(
            ['allocation' => $this->formatAllocation($allocation->load('loan:id,loan_number,principal_amount,status'))],
            'Allocation recorded.',
            201
        );
    }

    public function updateAllocation(Request $request, InvestorAllocation $allocation): JsonResponse
    {
        $data = $request->validate([
            'status'        => ['sometimes', 'in:active,settled,defaulted'],
            'actual_return' => ['sometimes', 'numeric', 'min:0'],
            'settled_date'  => ['nullable', 'date'],
            'notes'         => ['nullable', 'string'],
        ]);

        $allocation->update($data);

        return $this->success(['allocation' => $this->formatAllocation($allocation->fresh())]);
    }

    public function portfolio(Request $request): JsonResponse
    {
        $investors = Investor::where('status', 'active')
            ->with('allocations')
            ->get();

        $totalDeployed  = 0.0;
        $totalReturns   = 0.0;
        $activeCount    = 0;

        foreach ($investors as $investor) {
            $totalDeployed += (float) $investor->allocations->sum('allocated_amount');
            $totalReturns  += (float) $investor->allocations->sum('actual_return');
            $activeCount   += $investor->allocations->where('status', 'active')->count();
        }

        return $this->success([
            'investor_count'   => $investors->count(),
            'total_deployed'   => round($totalDeployed, 2),
            'total_returns'    => round($totalReturns, 2),
            'active_allocations' => $activeCount,
            'avg_return_rate'  => $totalDeployed > 0
                ? round(($totalReturns / $totalDeployed) * 100, 2)
                : 0,
        ]);
    }

    // ─── Formatters ───────────────────────────────────────────────────────────

    private function format(Investor $i): array
    {
        return [
            'id'               => $i->id,
            'investor_number'  => $i->investor_number,
            'name'             => $i->name,
            'email'            => $i->email,
            'phone'            => $i->phone,
            'type'             => $i->type,
            'national_id'      => $i->national_id,
            'address'          => $i->address,
            'country'          => $i->country,
            'status'           => $i->status,
            'notes'            => $i->notes,
            'allocations_count'=> $i->allocations_count ?? null,
            'total_allocated'  => $i->total_allocated,
            'total_returns'    => $i->total_returns,
        ];
    }

    private function formatAllocation(InvestorAllocation $a): array
    {
        return [
            'id'               => $a->id,
            'investor_id'      => $a->investor_id,
            'loan_id'          => $a->loan_id,
            'loan_number'      => $a->relationLoaded('loan') ? $a->loan->loan_number : null,
            'allocated_amount' => (float) $a->allocated_amount,
            'expected_return'  => (float) $a->expected_return,
            'actual_return'    => (float) $a->actual_return,
            'status'           => $a->status,
            'allocation_date'  => $a->allocation_date?->toDateString(),
            'settled_date'     => $a->settled_date?->toDateString(),
            'notes'            => $a->notes,
        ];
    }
}
