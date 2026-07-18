<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\CollectionLog;
use App\Models\Tenant\Loan;
use App\Models\Tenant\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    /**
     * GET /collections
     * Overdue loan queue with latest collection activity per loan.
     */
    public function index(Request $request): Response
    {
        // Resolve overdue loans: loans that have at least one unpaid overdue instalment
        $query = Loan::query()
            ->with([
                'borrower:id,first_name,last_name,borrower_number,phone,city',
                'loanType:id,name',
                'collectionLogs' => fn ($q) => $q->with('officer:id,name')->limit(1),
            ])
            ->whereHas('schedule', fn ($q) => $q->where('days_overdue', '>', 0)->where('is_paid', false))
            ->whereNotIn('status', ['completed', 'written_off', 'denied', 'draft'])
            ->when($request->officer_id, fn ($q, $id) => $q->whereHas('collectionLogs', fn ($lq) => $lq->where('officer_id', $id)))
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('loan_number', 'like', "%{$s}%")
                    ->orWhereHas('borrower', fn ($bq) => $bq->where('first_name', 'like', "%{$s}%")
                        ->orWhere('last_name', 'like', "%{$s}%")
                        ->orWhere('phone', 'like', "%{$s}%")
                        ->orWhere('borrower_number', 'like', "%{$s}%"),
                    );
            }));

        // PAR bucket filter
        if ($request->bucket) {
            $query->when($request->bucket, function ($q, $bucket) {
                $q->whereHas('schedule', function ($sq) use ($bucket) {
                    $sq->where('is_paid', false);
                    match ($bucket) {
                        '1_30' => $sq->whereBetween('days_overdue', [1, 30]),
                        '31_60' => $sq->whereBetween('days_overdue', [31, 60]),
                        '61_90' => $sq->whereBetween('days_overdue', [61, 90]),
                        '91plus' => $sq->where('days_overdue', '>', 90),
                        default => null,
                    };
                });
            });
        }

        $loans = $query->orderByDesc('updated_at')->paginate(25)->withQueryString();

        // For each loan get max days overdue and total outstanding from schedule
        $loanData = $loans->through(function ($loan) {
            $overdueSchedule = $loan->schedule()
                ->where('is_paid', false)
                ->where('days_overdue', '>', 0)
                ->selectRaw('MAX(days_overdue) as max_days, SUM(outstanding) as overdue_outstanding, COUNT(*) as overdue_count')
                ->first();

            $latestLog = $loan->collectionLogs->first();

            return [
                'id' => $loan->id,
                'loan_number' => $loan->loan_number,
                'status' => $loan->status->value,
                'borrower' => [
                    'id' => $loan->borrower->id,
                    'name' => $loan->borrower->full_name,
                    'borrower_number' => $loan->borrower->borrower_number,
                    'phone' => $loan->borrower->phone,
                    'city' => $loan->borrower->city,
                ],
                'loan_type' => $loan->loanType->name,
                'outstanding_balance' => number_format((float) $loan->outstanding_balance, 2),
                'penalty_balance' => number_format((float) $loan->penalty_balance, 2),
                'max_days_overdue' => (int) ($overdueSchedule->max_days ?? 0),
                'overdue_instalments' => (int) ($overdueSchedule->overdue_count ?? 0),
                'overdue_outstanding' => number_format((float) ($overdueSchedule->overdue_outstanding ?? 0), 2),
                'latest_log' => $latestLog ? [
                    'id' => $latestLog->id,
                    'contact_method' => $latestLog->contact_method,
                    'outcome' => $latestLog->outcome,
                    'outcome_label' => $latestLog->outcomeLabel(),
                    'outcome_color' => $latestLog->outcomeColor(),
                    'officer_name' => $latestLog->officer?->name,
                    'notes' => $latestLog->notes,
                    'follow_up_date' => $latestLog->follow_up_date?->format('d M Y'),
                    'created_at' => $latestLog->created_at->format('d M Y H:i'),
                ] : null,
            ];
        });

        // Summary stats
        $stats = $this->summaryStats();

        return Inertia::render('collections/Index', [
            'loans' => $loanData,
            'stats' => $stats,
            'officers' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'officer_id', 'bucket']),
        ]);
    }

    /**
     * GET /collections/{loan}
     * Full collection history for a single loan.
     */
    public function show(Loan $loan): Response
    {
        $loan->load([
            'borrower:id,first_name,last_name,borrower_number,phone,email,city,address',
            'loanType:id,name',
            'schedule',
            'collectionLogs.officer:id,name',
        ]);

        $overdueSchedule = $loan->schedule()
            ->where('is_paid', false)
            ->where('days_overdue', '>', 0)
            ->selectRaw('MAX(days_overdue) as max_days, SUM(outstanding) as overdue_outstanding, COUNT(*) as overdue_count')
            ->first();

        $logs = $loan->collectionLogs->map(fn ($log) => [
            'id' => $log->id,
            'contact_method' => $log->contact_method,
            'contact_label' => $log->contactMethodLabel(),
            'outcome' => $log->outcome,
            'outcome_label' => $log->outcomeLabel(),
            'outcome_color' => $log->outcomeColor(),
            'notes' => $log->notes,
            'follow_up_date' => $log->follow_up_date?->format('d M Y'),
            'amount_promised' => $log->amount_promised ? number_format((float) $log->amount_promised, 2) : null,
            'amount_collected' => $log->amount_collected ? number_format((float) $log->amount_collected, 2) : null,
            'officer_name' => $log->officer?->name ?? 'Unknown',
            'created_at' => $log->created_at->format('d M Y H:i'),
        ]);

        return Inertia::render('collections/Show', [
            'loan' => [
                'id' => $loan->id,
                'loan_number' => $loan->loan_number,
                'status' => $loan->status->value,
                'status_label' => $loan->status->label(),
                'loan_type' => $loan->loanType->name,
                'outstanding_balance' => number_format((float) $loan->outstanding_balance, 2),
                'penalty_balance' => number_format((float) $loan->penalty_balance, 2),
                'max_days_overdue' => (int) ($overdueSchedule->max_days ?? 0),
                'overdue_instalments' => (int) ($overdueSchedule->overdue_count ?? 0),
                'overdue_outstanding' => number_format((float) ($overdueSchedule->overdue_outstanding ?? 0), 2),
                'borrower' => [
                    'id' => $loan->borrower->id,
                    'name' => $loan->borrower->full_name,
                    'borrower_number' => $loan->borrower->borrower_number,
                    'phone' => $loan->borrower->phone,
                    'email' => $loan->borrower->email,
                    'city' => $loan->borrower->city,
                    'address' => $loan->borrower->address,
                ],
            ],
            'logs' => $logs,
            'officers' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * POST /collections/{loan}/logs
     * Record a new collection activity.
     */
    public function store(Request $request, Loan $loan): JsonResponse
    {
        $data = $request->validate([
            'contact_method' => ['required', 'in:call,sms,visit,email,whatsapp'],
            'outcome' => ['required', 'in:reached,no_answer,promised_payment,partial_payment,paid_up,refused,invalid_number,rescheduled'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'follow_up_date' => ['nullable', 'date', 'after:today'],
            'amount_promised' => ['nullable', 'numeric', 'min:0.01'],
            'amount_collected' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $log = CollectionLog::create([
            ...$data,
            'loan_id' => $loan->id,
            'officer_id' => auth()->id(),
        ]);

        $log->load('officer:id,name');

        return response()->json([
            'message' => 'Collection activity recorded.',
            'log' => [
                'id' => $log->id,
                'contact_method' => $log->contact_method,
                'contact_label' => $log->contactMethodLabel(),
                'outcome' => $log->outcome,
                'outcome_label' => $log->outcomeLabel(),
                'outcome_color' => $log->outcomeColor(),
                'notes' => $log->notes,
                'follow_up_date' => $log->follow_up_date?->format('d M Y'),
                'amount_promised' => $log->amount_promised ? number_format((float) $log->amount_promised, 2) : null,
                'amount_collected' => $log->amount_collected ? number_format((float) $log->amount_collected, 2) : null,
                'officer_name' => $log->officer?->name,
                'created_at' => $log->created_at->format('d M Y H:i'),
            ],
        ]);
    }

    /**
     * GET /collections/stats (JSON) — dashboard KPIs
     */
    public function stats(): JsonResponse
    {
        return response()->json($this->summaryStats());
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function summaryStats(): array
    {
        $totalOverdue = Loan::whereHas('schedule', fn ($q) => $q->where('days_overdue', '>', 0)->where('is_paid', false),
        )->whereNotIn('status', ['completed', 'written_off', 'denied', 'draft'])->count();

        $loggedToday = CollectionLog::whereDate('created_at', today())->count();

        $followUpToday = CollectionLog::whereDate('follow_up_date', today())->count();

        $promisedThisWeek = CollectionLog::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->where('outcome', 'promised_payment')
            ->sum('amount_promised');

        $collectedThisWeek = CollectionLog::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->whereIn('outcome', ['partial_payment', 'paid_up'])
            ->sum('amount_collected');

        return [
            'total_overdue' => $totalOverdue,
            'logged_today' => $loggedToday,
            'follow_up_today' => $followUpToday,
            'promised_this_week' => round((float) $promisedThisWeek, 2),
            'collected_this_week' => round((float) $collectedThisWeek, 2),
        ];
    }
}
