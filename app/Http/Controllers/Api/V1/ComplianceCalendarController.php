<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\ComplianceEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplianceCalendarController extends BaseApiController
{
    /**
     * GET /api/v1/compliance-events
     */
    public function index(Request $request): JsonResponse
    {
        // Auto-mark overdue
        ComplianceEvent::where('status', 'pending')
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);

        $query = ComplianceEvent::with('assignee')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->category, fn ($q, $v) => $q->where('category', $v))
            ->when($request->from, fn ($q, $v) => $q->whereDate('due_date', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('due_date', '<=', $v))
            ->orderBy('due_date');

        return $this->paginated(
            $query->paginate($request->integer('per_page', 20)),
            fn ($e) => $this->formatEvent($e),
        );
    }

    /**
     * POST /api/v1/compliance-events
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'in:regulatory,audit,tax,internal'],
            'description' => ['nullable', 'string'],
            'due_date' => ['required', 'date'],
            'frequency' => ['nullable', 'in:once,monthly,quarterly,annually'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $event = ComplianceEvent::create(array_merge(['status' => 'pending'], $data));

        return $this->success(['event' => $this->formatEvent($event)], 'Compliance event created.', 201);
    }

    /**
     * GET /api/v1/compliance-events/{event}
     */
    public function show(ComplianceEvent $complianceEvent): JsonResponse
    {
        return $this->success(['event' => $this->formatEvent($complianceEvent->load('assignee', 'completedBy'))]);
    }

    /**
     * PUT /api/v1/compliance-events/{event}
     */
    public function update(Request $request, ComplianceEvent $complianceEvent): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'category' => ['sometimes', 'in:regulatory,audit,tax,internal'],
            'description' => ['sometimes', 'nullable', 'string'],
            'due_date' => ['sometimes', 'date'],
            'frequency' => ['sometimes', 'in:once,monthly,quarterly,annually'],
            'assigned_to' => ['sometimes', 'nullable', 'exists:users,id'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $complianceEvent->update($data);

        return $this->success(['event' => $this->formatEvent($complianceEvent->fresh())], 'Compliance event updated.');
    }

    /**
     * DELETE /api/v1/compliance-events/{event}
     */
    public function destroy(ComplianceEvent $complianceEvent): JsonResponse
    {
        $complianceEvent->delete();

        return $this->success([], 'Compliance event deleted.');
    }

    /**
     * POST /api/v1/compliance-events/{event}/complete
     */
    public function complete(Request $request, ComplianceEvent $complianceEvent): JsonResponse
    {
        if ($complianceEvent->status === 'completed') {
            return $this->error('Event already completed.', 422);
        }

        $complianceEvent->update([
            'status' => 'completed',
            'completed_by' => auth()->id(),
            'completed_at' => now(),
            'notes' => $request->notes ?? $complianceEvent->notes,
        ]);

        // If recurring, spawn the next event
        if ($complianceEvent->frequency !== 'once') {
            $nextDue = match ($complianceEvent->frequency) {
                'monthly' => $complianceEvent->due_date->addMonth(),
                'quarterly' => $complianceEvent->due_date->addMonths(3),
                'annually' => $complianceEvent->due_date->addYear(),
                default => null,
            };

            if ($nextDue) {
                ComplianceEvent::create([
                    'title' => $complianceEvent->title,
                    'category' => $complianceEvent->category,
                    'description' => $complianceEvent->description,
                    'due_date' => $nextDue->toDateString(),
                    'frequency' => $complianceEvent->frequency,
                    'assigned_to' => $complianceEvent->assigned_to,
                ]);
            }
        }

        return $this->success(['event' => $this->formatEvent($complianceEvent->fresh())], 'Event marked as completed.');
    }

    /**
     * GET /api/v1/compliance-events/upcoming
     */
    public function upcoming(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);
        $events = ComplianceEvent::where('status', 'pending')
            ->whereDate('due_date', '>=', now()->toDateString())
            ->whereDate('due_date', '<=', now()->addDays($days)->toDateString())
            ->orderBy('due_date')
            ->get();

        return $this->success(['events' => $events->map(fn ($e) => $this->formatEvent($e))]);
    }

    // ─── Formatter ────────────────────────────────────────────────────────────

    private function formatEvent(ComplianceEvent $e): array
    {
        return [
            'id' => $e->id,
            'title' => $e->title,
            'category' => $e->category,
            'description' => $e->description,
            'due_date' => $e->due_date->toDateString(),
            'frequency' => $e->frequency,
            'status' => $e->status,
            'is_overdue' => $e->isOverdue(),
            'assigned_to' => $e->assigned_to,
            'completed_by' => $e->completed_by,
            'completed_at' => $e->completed_at?->toDateTimeString(),
            'notes' => $e->notes,
            'reminder_sent' => $e->reminder_sent,
        ];
    }
}
