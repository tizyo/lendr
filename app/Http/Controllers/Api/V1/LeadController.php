<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Borrower;
use App\Models\Tenant\BorrowerInteraction;
use App\Models\Tenant\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends BaseApiController
{
    // GET /api/v1/leads
    public function index(Request $request): JsonResponse
    {
        $leads = Lead::query()
            ->with(['assignedTo:id,name', 'convertedBorrower:id,borrower_number,first_name,last_name'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->assigned_to, fn ($q, $id) => $q->where('assigned_to', $id))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q2) => $q2
                ->where('first_name', 'like', "%{$s}%")
                ->orWhere('last_name', 'like', "%{$s}%")
                ->orWhere('phone', 'like', "%{$s}%")
                ->orWhere('lead_number', 'like', "%{$s}%"),
            ))
            ->when($request->follow_up_date, fn ($q, $d) => $q->whereDate('follow_up_date', $d))
            ->latest()
            ->paginate(25);

        return $this->success($leads);
    }

    // POST /api/v1/leads
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:150',
            'city' => 'nullable|string|max:100',
            'occupation' => 'nullable|string|max:100',
            'requested_amount' => 'nullable|numeric|min:0',
            'loan_purpose' => 'nullable|string|max:255',
            'source' => 'nullable|in:'.implode(',', Lead::sources()),
            'referral_name' => 'nullable|string|max:150',
            'assigned_to' => 'nullable|exists:users,id',
            'follow_up_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $lead = Lead::create(array_merge($data, [
            'lead_number' => Lead::generateLeadNumber(),
            'status' => 'new',
        ]));

        return $this->success(['lead' => $lead->load('assignedTo:id,name')], 'Lead created.', 201);
    }

    // GET /api/v1/leads/{lead}
    public function show(Lead $lead): JsonResponse
    {
        return $this->success(['lead' => $lead->load([
            'assignedTo:id,name',
            'convertedBorrower:id,borrower_number,first_name,last_name',
            'interactions.recordedBy:id,name',
        ])]);
    }

    // PUT /api/v1/leads/{lead}
    public function update(Request $request, Lead $lead): JsonResponse
    {
        $data = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'sometimes|string|max:30',
            'email' => 'nullable|email|max:150',
            'city' => 'nullable|string|max:100',
            'occupation' => 'nullable|string|max:100',
            'requested_amount' => 'nullable|numeric|min:0',
            'loan_purpose' => 'nullable|string|max:255',
            'source' => 'nullable|in:'.implode(',', Lead::sources()),
            'referral_name' => 'nullable|string|max:150',
            'status' => 'nullable|in:'.implode(',', Lead::statuses()),
            'lost_reason' => 'nullable|string|max:255',
            'assigned_to' => 'nullable|exists:users,id',
            'follow_up_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $lead->update($data);

        return $this->success(['lead' => $lead->fresh(['assignedTo:id,name'])]);
    }

    // DELETE /api/v1/leads/{lead}
    public function destroy(Lead $lead): JsonResponse
    {
        $lead->delete();

        return $this->success(null, 'Deleted.', 204);
    }

    // POST /api/v1/leads/{lead}/convert
    public function convert(Request $request, Lead $lead): JsonResponse
    {
        if ($lead->status === 'converted') {
            return $this->error('Lead is already converted.', 422);
        }

        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:150',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'city' => 'nullable|string|max:100',
            'occupation' => 'nullable|string|max:100',
        ]);

        $borrower = Borrower::create(array_merge($data, [
            'borrower_number' => Borrower::generateBorrowerNumber(),
            'is_active' => true,
        ]));

        $lead->update([
            'status' => 'converted',
            'converted_borrower_id' => $borrower->id,
        ]);

        return $this->success([
            'lead' => $lead->fresh(),
            'borrower' => $borrower,
        ]);
    }

    // GET /api/v1/leads/pipeline
    public function pipeline(): JsonResponse
    {
        $statuses = Lead::statuses();

        $pipeline = collect($statuses)->mapWithKeys(fn ($status) => [
            $status => [
                'count' => Lead::where('status', $status)->count(),
                'amount' => (float) Lead::where('status', $status)->sum('requested_amount'),
            ],
        ]);

        return $this->success($pipeline);
    }

    // POST /api/v1/leads/{lead}/interactions
    public function addInteraction(Request $request, Lead $lead): JsonResponse
    {
        $data = $request->validate([
            'channel' => 'required|in:'.implode(',', BorrowerInteraction::channels()),
            'direction' => 'required|in:inbound,outbound',
            'outcome' => 'required|in:'.implode(',', BorrowerInteraction::outcomes()),
            'notes' => 'nullable|string',
            'follow_up_date' => 'nullable|date',
            'amount_discussed' => 'nullable|numeric|min:0',
            'interaction_at' => 'nullable|date',
        ]);

        $interaction = BorrowerInteraction::create(array_merge($data, [
            'lead_id' => $lead->id,
            'recorded_by' => auth()->id(),
            'interaction_at' => $data['interaction_at'] ?? now(),
        ]));

        return $this->success(['interaction' => $interaction->load('recordedBy:id,name')], 'Interaction recorded.', 201);
    }
}
