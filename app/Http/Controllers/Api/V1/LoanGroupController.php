<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanGroup;
use App\Models\Tenant\LoanGroupMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanGroupController extends BaseApiController
{
    // ─── Groups ───────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/loan-groups
     */
    public function index(Request $request): JsonResponse
    {
        $query = LoanGroup::withCount(['activeMembers', 'loans'])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->officer_id, fn ($q, $v) => $q->where('officer_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('id');

        return $this->paginated(
            $query->paginate($request->integer('per_page', 20)),
            fn ($g) => $this->formatGroup($g),
        );
    }

    /**
     * POST /api/v1/loan-groups
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'officer_id' => ['nullable', 'exists:users,id'],
            'description' => ['nullable', 'string'],
            'meeting_schedule' => ['nullable', 'string', 'max:150'],
            'meeting_location' => ['nullable', 'string', 'max:150'],
            'max_members' => ['nullable', 'integer', 'min:2', 'max:200'],
        ]);

        $group = LoanGroup::create([
            ...$data,
            'group_number' => LoanGroup::generateGroupNumber(),
        ]);

        return $this->success(['group' => $this->formatGroup($group)], 'Group created.', 201);
    }

    /**
     * GET /api/v1/loan-groups/{group}
     */
    public function show(LoanGroup $loanGroup): JsonResponse
    {
        $loanGroup->loadCount(['activeMembers', 'loans']);
        $loanGroup->load(['officer', 'activeMembers.borrower']);

        return $this->success(['group' => $this->formatGroup($loanGroup, true)]);
    }

    /**
     * PUT /api/v1/loan-groups/{group}
     */
    public function update(Request $request, LoanGroup $loanGroup): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'officer_id' => ['nullable', 'exists:users,id'],
            'description' => ['nullable', 'string'],
            'meeting_schedule' => ['nullable', 'string', 'max:150'],
            'meeting_location' => ['nullable', 'string', 'max:150'],
            'status' => ['sometimes', 'in:active,inactive,dissolved'],
            'max_members' => ['nullable', 'integer', 'min:2', 'max:200'],
        ]);

        $loanGroup->update($data);

        return $this->success(['group' => $this->formatGroup($loanGroup->fresh())], 'Group updated.');
    }

    /**
     * DELETE /api/v1/loan-groups/{group}
     */
    public function destroy(LoanGroup $loanGroup): JsonResponse
    {
        $loanGroup->delete();

        return $this->success(null, 'Group deleted.');
    }

    // ─── Members ──────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/loan-groups/{group}/members
     */
    public function addMember(Request $request, LoanGroup $loanGroup): JsonResponse
    {
        $data = $request->validate([
            'borrower_id' => ['required', 'exists:borrowers,id'],
            'role' => ['nullable', 'in:leader,secretary,member'],
        ]);

        // Check capacity
        $activeCount = $loanGroup->activeMembers()->count();
        if ($activeCount >= $loanGroup->max_members) {
            return $this->error("Group is at capacity ({$loanGroup->max_members} members).", 422);
        }

        // Prevent duplicate active membership
        $existing = LoanGroupMember::where('loan_group_id', $loanGroup->id)
            ->where('borrower_id', $data['borrower_id'])
            ->where('is_active', true)
            ->first();

        if ($existing) {
            return $this->error('Borrower is already an active member of this group.', 422);
        }

        $member = LoanGroupMember::updateOrCreate(
            ['loan_group_id' => $loanGroup->id, 'borrower_id' => $data['borrower_id']],
            [
                'role' => $data['role'] ?? 'member',
                'is_active' => true,
                'joined_date' => now()->toDateString(),
                'left_date' => null,
            ],
        );

        return $this->success(['member' => $this->formatMember($member->load('borrower'))], 'Member added.', 201);
    }

    /**
     * DELETE /api/v1/loan-groups/{group}/members/{borrower}
     */
    public function removeMember(LoanGroup $loanGroup, Borrower $borrower): JsonResponse
    {
        $member = LoanGroupMember::where('loan_group_id', $loanGroup->id)
            ->where('borrower_id', $borrower->id)
            ->where('is_active', true)
            ->first();

        if (! $member) {
            return $this->error('Borrower is not an active member of this group.', 404);
        }

        $member->update(['is_active' => false, 'left_date' => now()->toDateString()]);

        return $this->success(null, 'Member removed.');
    }

    // ─── Formatters ───────────────────────────────────────────────────────────

    private function formatGroup(LoanGroup $g, bool $full = false): array
    {
        $data = [
            'id' => $g->id,
            'group_number' => $g->group_number,
            'name' => $g->name,
            'status' => $g->status,
            'officer_id' => $g->officer_id,
            'meeting_schedule' => $g->meeting_schedule,
            'meeting_location' => $g->meeting_location,
            'max_members' => $g->max_members,
            'active_members' => $g->active_members_count ?? null,
            'loans_count' => $g->loans_count ?? null,
        ];

        if ($full) {
            $data['officer'] = $g->relationLoaded('officer') ? ['id' => $g->officer?->id, 'name' => $g->officer?->name] : null;
            $data['members'] = $g->relationLoaded('activeMembers')
                ? $g->activeMembers->map(fn ($m) => $this->formatMember($m))->values()
                : [];
        }

        return $data;
    }

    private function formatMember(LoanGroupMember $m): array
    {
        return [
            'id' => $m->id,
            'borrower_id' => $m->borrower_id,
            'name' => $m->relationLoaded('borrower') ? $m->borrower->full_name : null,
            'role' => $m->role,
            'joined_date' => $m->joined_date->toDateString(),
        ];
    }
}
