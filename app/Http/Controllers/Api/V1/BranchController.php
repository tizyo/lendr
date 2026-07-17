<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Branch;
use App\Services\PlanFeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $branches = Branch::query()
            ->with('manager:id,name,email')
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('code', 'like', "%{$s}%")
                  ->orWhere('city', 'like', "%{$s}%");
            }))
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->paginate(25);

        return $this->paginated($branches, fn ($b) => $this->format($b));
    }

    public function store(Request $request): JsonResponse
    {
        $svc = PlanFeatureService::forTenant();
        if (! $svc->canAddBranch(Branch::count())) {
            return $this->error(
                'Your plan\'s branch limit has been reached. Upgrade your plan to add more branches.',
                403
            );
        }

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:150'],
            'code'       => ['required', 'string', 'max:20', 'unique:branches,code'],
            'address'    => ['nullable', 'string', 'max:255'],
            'city'       => ['nullable', 'string', 'max:100'],
            'country'    => ['nullable', 'string', 'max:100'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'email'      => ['nullable', 'email', 'max:150'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'is_active'  => ['boolean'],
            'notes'      => ['nullable', 'string', 'max:1000'],
        ]);

        $branch = Branch::create($data);
        $branch->load('manager:id,name,email');

        return $this->success($this->format($branch), 'Branch created.', 201);
    }

    public function show(int $id): JsonResponse
    {
        $branch = Branch::with('manager:id,name,email')->findOrFail($id);

        return $this->success($this->format($branch));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);

        $data = $request->validate([
            'name'       => ['sometimes', 'required', 'string', 'max:150'],
            'code'       => ['sometimes', 'required', 'string', 'max:20', Rule::unique('branches', 'code')->ignore($branch->id)],
            'address'    => ['nullable', 'string', 'max:255'],
            'city'       => ['nullable', 'string', 'max:100'],
            'country'    => ['nullable', 'string', 'max:100'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'email'      => ['nullable', 'email', 'max:150'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'is_active'  => ['boolean'],
            'notes'      => ['nullable', 'string', 'max:1000'],
        ]);

        $branch->update($data);
        $branch->load('manager:id,name,email');

        return $this->success($this->format($branch), 'Branch updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);
        $branch->delete();

        return $this->success(null, 'Branch deleted.');
    }

    private function format(Branch $branch): array
    {
        return [
            'id'         => $branch->id,
            'name'       => $branch->name,
            'code'       => $branch->code,
            'address'    => $branch->address,
            'city'       => $branch->city,
            'country'    => $branch->country,
            'phone'      => $branch->phone,
            'email'      => $branch->email,
            'is_active'  => $branch->is_active,
            'notes'      => $branch->notes,
            'manager'    => $branch->manager ? [
                'id'    => $branch->manager->id,
                'name'  => $branch->manager->name,
                'email' => $branch->manager->email,
            ] : null,
            'created_at' => $branch->created_at?->toDateTimeString(),
            'updated_at' => $branch->updated_at?->toDateTimeString(),
        ];
    }
}
