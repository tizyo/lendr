<?php

namespace App\Http\Controllers\Api\V1\Landlord;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Landlord\SupportTicket;
use App\Models\Landlord\SupportTicketReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportController extends BaseApiController
{
    // ─── List all tickets ─────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = SupportTicket::with('tenant:id,name,slug')
            ->withCount('replies')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }
        if ($request->filled('search')) {
            $query->where('subject', 'like', '%'.$request->search.'%');
        }

        $tickets = $query->paginate(25)->through(fn ($t) => $this->format($t));

        return $this->success($tickets);
    }

    // ─── Show single ticket with thread ──────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $ticket = SupportTicket::with(['tenant:id,name,slug', 'replies'])
            ->findOrFail($id);

        return $this->success($this->formatFull($ticket));
    }

    // ─── Landlord reply ───────────────────────────────────────────────────────

    public function reply(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::findOrFail($id);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'author_type' => 'landlord',
            'author_name' => $request->user()?->name ?? 'LENDR Support',
            'message' => $data['message'],
        ]);

        // Auto-move to in_progress when landlord first replies on an open ticket
        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        return $this->success($this->formatFull($ticket->fresh(['replies'])), 'Reply sent.');
    }

    // ─── Update status ────────────────────────────────────────────────────────

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'in:open,in_progress,resolved,closed'],
        ]);

        $ticket->update([
            'status' => $data['status'],
            'resolved_at' => in_array($data['status'], ['resolved', 'closed']) ? now() : null,
        ]);

        return $this->success($this->format($ticket->fresh()), 'Status updated.');
    }

    // ─── Update priority ──────────────────────────────────────────────────────

    public function updatePriority(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::findOrFail($id);

        $data = $request->validate([
            'priority' => ['required', 'in:low,medium,high,critical'],
        ]);

        $ticket->update(['priority' => $data['priority']]);

        return $this->success($this->format($ticket->fresh()), 'Priority updated.');
    }

    // ─── Stats summary ────────────────────────────────────────────────────────

    public function stats(): JsonResponse
    {
        $tickets = SupportTicket::all();

        return $this->success([
            'total' => $tickets->count(),
            'by_status' => $tickets->groupBy('status')->map->count(),
            'by_type' => $tickets->groupBy('type')->map->count(),
            'by_priority' => $tickets->groupBy('priority')->map->count(),
            'open' => $tickets->whereIn('status', ['open', 'in_progress'])->count(),
        ]);
    }

    // ─── Formatters ──────────────────────────────────────────────────────────

    private function format(SupportTicket $t): array
    {
        return [
            'id' => $t->id,
            'subject' => $t->subject,
            'type' => $t->type,
            'status' => $t->status,
            'priority' => $t->priority,
            'tenant_id' => $t->tenant_id,
            'tenant_name' => $t->tenant?->name,
            'submitted_by' => $t->submitted_by,
            'replies_count' => $t->replies_count ?? $t->replies()->count(),
            'created_at' => $t->created_at->toDateString(),
            'resolved_at' => $t->resolved_at?->toDateString(),
        ];
    }

    private function formatFull(SupportTicket $t): array
    {
        return array_merge($this->format($t), [
            'message' => $t->message,
            'submitted_by_email' => $t->submitted_by_email,
            'replies' => $t->replies->map(fn ($r) => [
                'id' => $r->id,
                'author_type' => $r->author_type,
                'author_name' => $r->author_name,
                'message' => $r->message,
                'created_at' => $r->created_at->format('d M Y, H:i'),
            ])->all(),
        ]);
    }
}
