<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendBroadcastMessageJob;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('broadcast/Index', [
            'counts' => $this->audienceCounts(),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'audience'  => ['required', 'in:all,active_loan,overdue'],
            'channels'  => ['required', 'array', 'min:1'],
            'channels.*'=> ['in:sms,email'],
            'subject'   => ['required_if:channels.*,email', 'nullable', 'string', 'max:200'],
            'message'   => ['required', 'string', 'max:1600'],
        ]);

        $subject  = $data['subject'] ?? config('app.name') . ' — Message';
        $channels = $data['channels'];
        $message  = $data['message'];

        $borrowerIds = $this->resolveAudience($data['audience']);

        foreach ($borrowerIds as $id) {
            SendBroadcastMessageJob::dispatch($id, $channels, $subject, $message);
        }

        $total = count($borrowerIds);
        $via   = implode(' & ', array_map('strtoupper', $channels));

        return back()->with('success', "Broadcast queued for {$total} customer(s) via {$via}.");
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function audienceCounts(): array
    {
        return [
            'all'         => Borrower::where('is_active', true)->count(),
            'active_loan' => Borrower::whereHas('loans', fn ($q) => $q->whereIn('status', ['active', 'disbursed']))->count(),
            'overdue'     => Borrower::whereHas('loans', fn ($q) => $q->whereHas('schedule', fn ($sq) =>
                                $sq->where('days_overdue', '>', 0)->where('is_paid', false)
                            ))->count(),
        ];
    }

    private function resolveAudience(string $audience): array
    {
        return match ($audience) {
            'active_loan' => Borrower::whereHas('loans', fn ($q) => $q->whereIn('status', ['active', 'disbursed']))
                ->where('is_active', true)
                ->pluck('id')
                ->toArray(),

            'overdue'     => Borrower::whereHas('loans', fn ($q) => $q->whereHas('schedule', fn ($sq) =>
                                $sq->where('days_overdue', '>', 0)->where('is_paid', false)
                             ))
                ->where('is_active', true)
                ->pluck('id')
                ->toArray(),

            default       => Borrower::where('is_active', true)->pluck('id')->toArray(),
        };
    }
}
