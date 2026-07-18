<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Agent;
use App\Models\Tenant\AgentCommission;
use App\Models\Tenant\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        if ($agentCommission->status !== 'pending') {
            return $this->error('Only pending commissions can be approved.', 422);
        }

        $agentCommission->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
        ]);

        return $this->success(['commission' => $agentCommission->fresh()]);
    }

    // POST /api/v1/agent-commissions/{commission}/pay
    public function payCommission(Request $request, AgentCommission $agentCommission): JsonResponse
    {
        if ($agentCommission->status !== 'approved') {
            return $this->error('Only approved commissions can be paid.', 422);
        }

        $data = $request->validate([
            'paid_date' => 'required|date',
            'payment_reference' => 'nullable|string|max:100',
        ]);

        $agentCommission->update(array_merge($data, [
            'status' => 'paid',
            'paid_by' => auth()->id(),
        ]));

        return $this->success(['commission' => $agentCommission->fresh()]);
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
