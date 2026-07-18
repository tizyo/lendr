<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Guarantor;
use App\Models\Tenant\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuarantorController extends BaseApiController
{
    /**
     * GET /api/v1/loans/{loan}/guarantors
     */
    public function index(Loan $loan): JsonResponse
    {
        return $this->success(
            $loan->guarantors()->get()->map(fn ($g) => $this->format($g)),
        );
    }

    /**
     * POST /api/v1/loans/{loan}/guarantors
     */
    public function store(Request $request, Loan $loan): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'relationship' => ['nullable', 'string', 'max:64'],
            'employer' => ['nullable', 'string', 'max:255'],
            'monthly_income' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $guarantor = $loan->guarantors()->create($data);

        return $this->success($this->format($guarantor), 'Guarantor added.', 201);
    }

    /**
     * GET /api/v1/guarantors/{guarantor}
     */
    public function show(Guarantor $guarantor): JsonResponse
    {
        return $this->success($this->format($guarantor));
    }

    /**
     * PUT /api/v1/guarantors/{guarantor}
     */
    public function update(Request $request, Guarantor $guarantor): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'relationship' => ['nullable', 'string', 'max:64'],
            'employer' => ['nullable', 'string', 'max:255'],
            'monthly_income' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:pending,approved,rejected'],
            'notes' => ['nullable', 'string'],
        ]);

        $guarantor->update($data);

        return $this->success($this->format($guarantor), 'Guarantor updated.');
    }

    /**
     * DELETE /api/v1/guarantors/{guarantor}
     */
    public function destroy(Guarantor $guarantor): JsonResponse
    {
        $guarantor->delete();

        return $this->success(null, 'Guarantor removed.');
    }

    // ─── Format ───────────────────────────────────────────────────────────────

    private function format(Guarantor $g): array
    {
        return [
            'id' => $g->id,
            'loan_id' => $g->loan_id,
            'name' => $g->name,
            'national_id' => $g->national_id,
            'phone' => $g->phone,
            'email' => $g->email,
            'address' => $g->address,
            'relationship' => $g->relationship,
            'employer' => $g->employer,
            'monthly_income' => $g->monthly_income ? (float) $g->monthly_income : null,
            'status' => $g->status,
            'status_badge' => $g->statusBadge(),
            'notes' => $g->notes,
            'created_at' => $g->created_at?->toDateString(),
        ];
    }
}
