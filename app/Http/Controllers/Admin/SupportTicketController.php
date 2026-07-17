<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Landlord\SupportTicket;
use App\Models\Landlord\SupportTicketReply;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller
{
    // ─── List ─────────────────────────────────────────────────────────────────

    public function index(): Response
    {
        $tenantId = tenancy()->tenant->id;

        $tickets = SupportTicket::where('tenant_id', $tenantId)
            ->withCount('replies')
            ->latest()
            ->get()
            ->map(fn ($t) => $this->format($t));

        return Inertia::render('support/Index', [
            'tickets' => $tickets,
        ]);
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show(int $id): Response
    {
        $tenantId = tenancy()->tenant->id;

        $ticket = SupportTicket::where('tenant_id', $tenantId)
            ->with('replies')
            ->findOrFail($id);

        return Inertia::render('support/Show', [
            'ticket' => $this->formatFull($ticket),
        ]);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject'  => ['required', 'string', 'max:255'],
            'message'  => ['required', 'string', 'max:5000'],
            'type'     => ['required', 'in:support,bug,feature'],
            'priority' => ['required', 'in:low,medium,high,critical'],
        ]);

        $tenant = tenancy()->tenant;
        $user   = $request->user();

        $ticket = SupportTicket::create([
            'tenant_id'          => $tenant->id,
            'subject'            => $data['subject'],
            'message'            => $data['message'],
            'type'               => $data['type'],
            'priority'           => $data['priority'],
            'status'             => 'open',
            'submitted_by'       => $user?->name,
            'submitted_by_email' => $user?->email,
        ]);

        return redirect()->route('support.show', $ticket->id)
            ->with('success', 'Ticket submitted successfully.');
    }

    // ─── Reply ────────────────────────────────────────────────────────────────

    public function reply(Request $request, int $id)
    {
        $tenantId = tenancy()->tenant->id;

        $ticket = SupportTicket::where('tenant_id', $tenantId)->findOrFail($id);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        SupportTicketReply::create([
            'ticket_id'   => $ticket->id,
            'author_type' => 'tenant',
            'author_name' => $request->user()?->name ?? 'Tenant',
            'message'     => $data['message'],
        ]);

        // Re-open if it was resolved
        if (in_array($ticket->status, ['resolved', 'closed'])) {
            $ticket->update(['status' => 'open', 'resolved_at' => null]);
        }

        return back()->with('success', 'Reply sent.');
    }

    // ─── Formatters ───────────────────────────────────────────────────────────

    private function format(SupportTicket $t): array
    {
        return [
            'id'           => $t->id,
            'subject'      => $t->subject,
            'type'         => $t->type,
            'status'       => $t->status,
            'priority'     => $t->priority,
            'replies_count' => $t->replies_count,
            'created_at'   => $t->created_at->toDateString(),
        ];
    }

    private function formatFull(SupportTicket $t): array
    {
        return [
            'id'                 => $t->id,
            'subject'            => $t->subject,
            'message'            => $t->message,
            'type'               => $t->type,
            'status'             => $t->status,
            'priority'           => $t->priority,
            'submitted_by'       => $t->submitted_by,
            'submitted_by_email' => $t->submitted_by_email,
            'created_at'         => $t->created_at->toDateString(),
            'resolved_at'        => $t->resolved_at?->toDateString(),
            'replies'            => $t->replies->map(fn ($r) => [
                'id'          => $r->id,
                'author_type' => $r->author_type,
                'author_name' => $r->author_name,
                'message'     => $r->message,
                'created_at'  => $r->created_at->format('d M Y, H:i'),
            ])->all(),
        ];
    }
}
