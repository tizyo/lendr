<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Agent;
use App\Models\Tenant\AgentCommission;
use App\Models\Tenant\FundBalance;
use App\Models\Tenant\Loan;
use App\Services\FundService;
use App\Services\GlLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentController extends BaseApiController
{
    // GET /api/v1/agents
    public function index(Request $request): JsonResponse
    {
        $agents = Agent::query()
            ->with('managedBy:id,name')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q2) => $q2
                ->where('first_name', 'like', "%{$s}%")
                ->orWhere('last_name', 'like', "%{$s}%")
                ->orWhere('phone', 'like', "%{$s}%")
                ->orWhere('agent_number', 'like', "%{$s}%"),
            ))
            ->latest()
            ->paginate(25);

        return $this->success($agents);
    }

    // POST /api/v1/agents
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'required|string|max:30|unique:agents,phone',
            'email' => 'nullable|email|max:150|unique:agents,email',
            'national_id' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'commission_type' => 'nullable|in:percentage,fixed',
            'fixed_commission' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:'.implode(',', Agent::statuses()),
            'managed_by' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $agent = Agent::create(array_merge($data, [
            'agent_number' => Agent::generateAgentNumber(),
            'status' => $data['status'] ?? 'active',
        ]));

        return $this->success(['agent' => $agent->load('managedBy:id,name')], 'Agent created.', 201);
    }

    // GET /api/v1/agents/{agent}
    public function show(Agent $agent): JsonResponse
    {
        $agent->load('managedBy:id,name');
        $agent->loadCount('loans');

        return $this->success(['agent' => $agent]);
    }

    // PUT /api/v1/agents/{agent}
    public function update(Request $request, Agent $agent): JsonResponse
    {
        $data = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'sometimes|string|max:30|unique:agents,phone,'.$agent->id,
            'email' => 'nullable|email|max:150|unique:agents,email,'.$agent->id,
            'national_id' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'commission_type' => 'nullable|in:percentage,fixed',
            'fixed_commission' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:'.implode(',', Agent::statuses()),
            'managed_by' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $agent->update($data);

        return $this->success(['agent' => $agent->fresh(['managedBy:id,name'])]);
    }

    // DELETE /api/v1/agents/{agent}
    public function destroy(Agent $agent): JsonResponse
    {
        $agent->delete();

        return $this->success(null, 'Deleted.', 204);
    }

    // GET /api/v1/agents/{agent}/commissions
    public function commissions(Agent $agent): JsonResponse
    {
        $commissions = $agent->commissions()
            ->with('loan:id,loan_number')
            ->latest()
            ->paginate(25);

        return $this->success($commissions);
    }

    // POST /api/v1/agent-commissions/{commission}/approve
    public function approveCommission(Request $request, AgentCommission $agentCommission): JsonResponse
    {
        // Conditional update: two approvers clicking at once can't both succeed.
        $updated = AgentCommission::whereKey($agentCommission->id)->where('status', 'pending')
            ->update(['status' => 'approved', 'approved_by' => auth()->id(), 'updated_at' => now()]);

        if (! $updated) {
            return $this->error('Only pending commissions can be approved.', 422);
        }

        return $this->success(['commission' => $agentCommission->fresh()]);
    }

    // POST /api/v1/agent-commissions/{commission}/pay
    public function payCommission(Request $request, AgentCommission $agentCommission): JsonResponse
    {
        $data = $request->validate([
            'paid_date' => 'required|date|before_or_equal:today',
            'payment_reference' => 'nullable|string|max:100',
        ]);

        // One unit: the commission is marked paid, the money leaves the fund and the
        // ledger records it, or none of it happens.
        $error = DB::transaction(function () use ($agentCommission, $data) {
            // Locked and re-checked: a double click must not pay (or debit the fund) twice.
            $commission = AgentCommission::whereKey($agentCommission->id)->lockForUpdate()->first();
            if (! $commission || $commission->status !== 'approved') {
                return 'Only approved commissions can be paid.';
            }

            $amount = (string) $commission->commission_amount;
            $available = (string) (FundBalance::lockForUpdate()->first()?->available_balance ?? 0);
            if (bccomp($available, $amount, 2) < 0) {
                return sprintf(
                    'Not enough in the fund to pay this commission: K %s needed, K %s available. Record a fund deposit first (Funding → Fund Management).',
                    number_format((float) $amount, 2),
                    number_format((float) $available, 2),
                );
            }

            $commission->update([...$data, 'status' => 'paid', 'paid_by' => auth()->id()]);
            $commission->loadMissing(['agent:id,agent_number,first_name,last_name', 'loan:id,loan_number']);

            $description = trim(sprintf(
                'Agent commission: %s %s (%s) — loan %s',
                $commission->agent?->first_name,
                $commission->agent?->last_name,
                $commission->agent?->agent_number,
                $commission->loan?->loan_number ?? '#'.$commission->loan_id,
            ));

            app(FundService::class)->recordAgentCommission($commission, auth()->id(), $description);
            app(GlLedgerService::class)->postAgentCommission($commission, $description);

            return null;
        });

        if ($error) {
            return $this->error($error, 422);
        }

        return $this->success(['commission' => $agentCommission->fresh()], 'Commission paid and posted to the ledger.');
    }

    // GET /api/v1/agents/{agent}/loans
    public function loans(Agent $agent): JsonResponse
    {
        $loans = Loan::where('agent_id', $agent->id)
            ->with('borrower:id,borrower_number,first_name,last_name')
            ->latest()
            ->paginate(25);

        return $this->success($loans);
    }

    // GET /api/v1/agents/commissions — all commissions
    public function allCommissions(Request $request): JsonResponse
    {
        $commissions = AgentCommission::query()
            ->with(['agent:id,agent_number,first_name,last_name', 'loan:id,loan_number'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->agent_id, fn ($q, $id) => $q->where('agent_id', $id))
            ->latest()
            ->paginate(25);

        return $this->success($commissions);
    }
}
