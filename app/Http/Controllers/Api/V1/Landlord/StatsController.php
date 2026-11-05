<?php

namespace App\Http\Controllers\Api\V1\Landlord;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Landlord\EmailLog;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\SubscriptionInvoice;
use App\Models\Landlord\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class StatsController extends BaseApiController
{
    /**
     * GET /v1/landlord/stats — platform-level KPIs for the landlord dashboard.
     */
    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        $tenants = Tenant::all();

        if ($request->boolean('fresh')) {
            Cache::forget(self::USAGE_CACHE_KEY);
        }

        $usage = $this->usage($tenants);

        return $this->success([
            'tenants' => $this->tenantStats($tenants),
            'revenue' => $this->revenueStats(),
            'growth' => $this->growthStats($tenants),
            'recent_invoices' => $this->recentInvoices(),
            'overview' => $this->overview($tenants, $usage),
            'monthly' => $this->monthly($tenants),
            'usage' => $usage,
            'attention' => $this->attention($tenants),
            'recent_signups' => $tenants->sortByDesc('created_at')->take(6)->values()->map(fn (Tenant $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'plan' => $t->plan,
                'status' => $t->status,
                'verified' => (bool) $t->email_verified_at,
                'created_at' => $t->created_at?->toIso8601String(),
            ])->all(),
            'email' => $this->emailHealth(),
        ]);
    }

    private const USAGE_CACHE_KEY = 'landlord:dashboard:usage:v1';

    /** Loans that still have money out. */
    private const OPEN_LOAN_STATUSES = ['disbursed', 'active', 'defaulted', 'frozen'];

    // ─── Headline numbers ──────────────────────────────────────────────────────

    private function overview($tenants, array $usage): array
    {
        $monthStart = now()->startOfMonth();
        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth();

        $paid = fn ($from, $to) => (float) SubscriptionInvoice::where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])->sum('amount');

        $pending = SubscriptionInvoice::where('status', 'pending');

        return [
            'workspaces' => $tenants->count(),
            'active_7d' => collect($usage['workspaces'])->filter(fn ($w) => $w['last_active'] && now()->diffInDays($w['last_active'], true) <= 7)->count(),
            'new_this_month' => $tenants->where('created_at', '>=', $monthStart)->count(),
            'new_last_month' => $tenants->whereBetween('created_at', [$lastMonthStart, $monthStart])->count(),
            'unverified' => $tenants->whereNull('email_verified_at')->count(),
            'verified_badge' => $tenants->where('is_verified', true)->count(),
            'revenue_this_month' => round($paid($monthStart, now()), 2),
            'revenue_last_month' => round($paid($lastMonthStart, $monthStart), 2),
            'pending_invoices' => ['count' => (clone $pending)->count(), 'amount' => round((float) (clone $pending)->sum('amount'), 2)],
            'support_open' => \App\Models\Landlord\SupportTicket::whereIn('status', ['open', 'in_progress'])->count(),
        ];
    }

    /** Twelve calendar months, oldest first, every month present (zeros included). */
    private function monthly($tenants): array
    {
        $from = now()->subMonths(11)->startOfMonth();

        $revenue = SubscriptionInvoice::where('status', 'paid')->where('paid_at', '>=', $from)->get(['amount', 'paid_at'])
            ->groupBy(fn ($i) => $i->paid_at->format('Y-m'))->map(fn ($g) => round((float) $g->sum('amount'), 2));

        $signups = $tenants->where('created_at', '>=', $from)
            ->groupBy(fn ($t) => $t->created_at->format('Y-m'))->map->count();

        return collect(range(0, 11))->map(function ($i) use ($from, $revenue, $signups) {
            $key = $from->copy()->addMonthsNoOverflow($i)->format('Y-m');

            return ['month' => $key, 'signups' => (int) ($signups[$key] ?? 0), 'revenue' => (float) ($revenue[$key] ?? 0)];
        })->all();
    }

    /**
     * What each workspace holds and when it was last used. Every workspace has its
     * own database, so this is cached for ten minutes ("Refresh" passes ?fresh=1).
     */
    private function usage($tenants): array
    {
        $rows = Cache::remember(self::USAGE_CACHE_KEY, 600, fn () => $tenants
            ->map(fn (Tenant $tenant) => ['id' => $tenant->id, ...$this->workspaceUsage($tenant)])
            ->all());

        $tenantsById = $tenants->keyBy('id');

        $workspaces = collect($rows)
            ->filter(fn ($r) => $tenantsById->has($r['id']))
            ->map(function ($r) use ($tenantsById) {
                $t = $tenantsById[$r['id']];

                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'plan' => $t->plan,
                    'status' => $t->status,
                    'currency' => $t->currency,
                    'reachable' => (bool) ($r['reachable'] ?? false),
                    'staff' => (int) ($r['staff'] ?? 0),
                    'borrowers' => (int) ($r['borrowers'] ?? 0),
                    'active_loans' => (int) ($r['active_loans'] ?? 0),
                    'outstanding' => (float) ($r['outstanding'] ?? 0),
                    'disbursed_30d' => (float) ($r['disbursed_30d'] ?? 0),
                    'last_active' => ! empty($r['last_active']) ? \Illuminate\Support\Carbon::parse($r['last_active'])->toIso8601String() : null,
                ];
            })
            ->sortByDesc(fn ($w) => [$w['outstanding'], $w['borrowers']])
            ->values();

        return [
            'workspaces' => $workspaces->all(),
            'totals' => [
                'staff' => $workspaces->sum('staff'),
                'borrowers' => $workspaces->sum('borrowers'),
                'active_loans' => $workspaces->sum('active_loans'),
                // Per currency: workspaces can lend in different ones.
                'outstanding' => $workspaces->groupBy('currency')->map(fn ($g) => round($g->sum('outstanding'), 2))->all(),
                'disbursed_30d' => $workspaces->groupBy('currency')->map(fn ($g) => round($g->sum('disbursed_30d'), 2))->all(),
                'unreachable' => $workspaces->where('reachable', false)->count(),
            ],
            'as_of' => now()->toIso8601String(),
        ];
    }

    /** One workspace's figures, read from its own database; a database that can't be read is marked, not fatal. */
    private function workspaceUsage(Tenant $tenant): array
    {
        $read = fn () => $tenant->run(fn () => [
            'staff' => \App\Models\Tenant\User::count(),
            'borrowers' => \App\Models\Tenant\Borrower::count(),
            'active_loans' => \App\Models\Tenant\Loan::whereIn('status', self::OPEN_LOAN_STATUSES)->count(),
            'outstanding' => round((float) \App\Models\Tenant\Loan::whereIn('status', self::OPEN_LOAN_STATUSES)->sum('outstanding_balance'), 2),
            'disbursed_30d' => round((float) \App\Models\Tenant\Loan::where('disbursed_at', '>=', now()->subDays(30))->sum('principal_amount'), 2),
            // Before the login-tracking migration has run there is no column to read.
            'last_active' => rescue(fn () => \App\Models\Tenant\User::max('last_seen_at'), null, false),
            'reachable' => true,
        ]);

        return rescue($read, ['reachable' => false]);
    }

    /** Things the landlord should act on, most serious first. */
    private function attention($tenants): array
    {
        $items = collect();

        foreach ($tenants as $t) {
            $row = fn (string $severity, string $type, string $message, $at = null) => [
                'severity' => $severity, 'type' => $type, 'tenant_id' => $t->id, 'tenant_name' => $t->name,
                'message' => $message, 'at' => ($at ?? $t->updated_at)?->toIso8601String(),
            ];

            if ($t->status === 'suspended') {
                $items->push($row('critical', 'suspended', 'Suspended: nobody can sign in.'));
            } elseif (! $t->email_verified_at) {
                $items->push($row('serious', 'unverified', 'Owner never verified their email, so nobody can sign in. Resend the link or verify them.', $t->created_at));
            } elseif ($t->status === 'expired' || ($t->status === 'trial' && $t->trial_ends_at?->isPast())) {
                $items->push($row('serious', 'expired', 'Trial ended without a plan.', $t->trial_ends_at));
            } elseif ($t->status === 'trial' && $t->trial_ends_at && $t->trial_ends_at->lte(now()->addDays(7))) {
                $days = (int) ceil(now()->diffInHours($t->trial_ends_at, true) / 24);
                $items->push($row('warning', 'trial_ending', 'Trial ends in '.$days.' day'.($days === 1 ? '' : 's').'.', $t->trial_ends_at));
            }
        }

        $names = $tenants->pluck('name', 'id');

        EmailLog::where('created_at', '>=', now()->subDays(7))
            ->where(fn ($q) => $q->whereIn('status', ['failed', 'bounced', 'complained'])
                ->orWhere(fn ($q2) => $q2->where('status', 'sending')->where('created_at', '<', now()->subMinutes(2))))
            ->latest('id')->limit(10)
            ->get(['id', 'tenant_id', 'to_email', 'subject', 'status', 'created_at'])
            ->each(fn ($log) => $items->push([
                'severity' => 'serious',
                'type' => 'email_failed',
                'tenant_id' => $log->tenant_id,
                'tenant_name' => $names[$log->tenant_id] ?? null,
                'message' => 'Email "'.\Illuminate\Support\Str::limit((string) $log->subject, 60).'" to '.$log->to_email.' '.($log->displayStatus() === 'bounced' ? 'bounced' : 'was not delivered').'.',
                'at' => $log->created_at?->toIso8601String(),
            ]));

        $rank = ['critical' => 0, 'serious' => 1, 'warning' => 2];

        return $items->sortBy(fn ($i) => [$rank[$i['severity']], -strtotime((string) $i['at'])])->values()->take(15)->all();
    }

    /** The last seven days of email to workspaces (see EmailLog). */
    private function emailHealth(): array
    {
        $logs = EmailLog::where('created_at', '>=', now()->subDays(7))->get(['id', 'status', 'provider', 'provider_id', 'created_at']);
        $statuses = $logs->map->displayStatus();

        $delivered = $statuses->filter(fn ($s) => in_array($s, ['delivered', 'opened', 'clicked'], true))->count();
        $failed = $statuses->filter(fn ($s) => in_array($s, ['failed', 'bounced', 'complained'], true))->count();

        return [
            'total' => $logs->count(),
            'delivered' => $delivered,
            'failed' => $failed,
            'pending' => $logs->count() - $delivered - $failed,
        ];
    }

    // ─── Tenant counts & distribution ─────────────────────────────────────────

    private function tenantStats($tenants): array
    {
        $byPlan = $tenants->groupBy('plan')->map->count();
        $byStatus = $tenants->groupBy('status')->map->count();
        $total = $tenants->count();

        // Trial conversion rate — 90-day cohort
        $cohortCutoff = now()->subDays(90);
        $cohortTenants = $tenants->where('created_at', '>=', $cohortCutoff);
        $cohortTotal = $cohortTenants->count();
        $cohortConverted = $cohortTenants->where('status', 'active')->count();
        $conversionRate = $cohortTotal > 0
            ? round($cohortConverted / $cohortTotal * 100, 1)
            : 0.0;

        // Monthly churn rate — tenants that churned (expired/cancelled) this calendar month
        $monthStart = now()->startOfMonth();
        $churned = $tenants
            ->whereIn('status', ['expired', 'cancelled'])
            ->where('updated_at', '>=', $monthStart)
            ->count();
        $activeAtMonthStart = $tenants->where('created_at', '<', $monthStart)->count();
        $churnRate = $activeAtMonthStart > 0
            ? round($churned / $activeAtMonthStart * 100, 1)
            : 0.0;

        return [
            'total' => $total,
            'by_plan' => $byPlan,
            'by_status' => $byStatus,
            'new_this_month' => $tenants
                ->where('created_at', '>=', $monthStart)
                ->count(),
            'trial_conversion_rate' => $conversionRate,   // % — 90-day cohort
            'monthly_churn_rate' => $churnRate,         // % — this month
        ];
    }

    // ─── Revenue metrics ───────────────────────────────────────────────────────

    private function revenueStats(): array
    {
        // MRR from currently active subscriptions (normalise annual to monthly)
        $activeSubscriptions = Subscription::where('status', 'active')
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->get();

        $mrr = $activeSubscriptions->sum(function ($sub) {
            return $sub->billing_cycle === 'annual'
                ? (float) $sub->amount / 12
                : (float) $sub->amount;
        });

        $arr = $mrr * 12;

        // Revenue trend — last 6 full calendar months + current partial month
        $trendFrom = now()->subMonths(5)->startOfMonth();
        $invoices = SubscriptionInvoice::where('status', 'paid')
            ->where('paid_at', '>=', $trendFrom)
            ->whereNotNull('paid_at')
            ->get();

        $trend = $invoices
            ->groupBy(fn ($i) => $i->paid_at->format('Y-m'))
            ->map(fn ($group) => round($group->sum('amount'), 2))
            ->sortKeys();

        // Revenue by plan (from active subscriptions, normalised to monthly)
        $byPlan = $activeSubscriptions
            ->groupBy('plan')
            ->map(fn ($subs) => round($subs->sum(function ($s) {
                return $s->billing_cycle === 'annual'
                    ? (float) $s->amount / 12
                    : (float) $s->amount;
            }), 2));

        // Total revenue all-time
        $totalRevenue = SubscriptionInvoice::where('status', 'paid')->sum('amount');

        return [
            'mrr' => round($mrr, 2),
            'arr' => round($arr, 2),
            'total_revenue' => round((float) $totalRevenue, 2),
            'trend' => $trend,          // { "2026-01": 2998.00, ... }
            'by_plan' => $byPlan,         // { "growth": 1499.00, ... }
        ];
    }

    // ─── Growth metrics ────────────────────────────────────────────────────────

    private function growthStats($tenants): array
    {
        // New tenant signups by month — last 6 months
        $from = now()->subMonths(5)->startOfMonth();
        $trend = $tenants
            ->where('created_at', '>=', $from)
            ->groupBy(fn ($t) => $t->created_at->format('Y-m'))
            ->map->count()
            ->sortKeys();

        return [
            'signup_trend' => $trend,   // { "2026-01": 4, "2026-02": 7, ... }
        ];
    }

    // ─── Recent invoices ───────────────────────────────────────────────────────

    private function recentInvoices(): array
    {
        return SubscriptionInvoice::where('status', 'paid')
            ->whereNotNull('paid_at')
            ->with('tenant:id,name,slug')
            ->latest('paid_at')
            ->limit(5)
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'tenant_name' => $inv->tenant?->name,
                'plan' => $inv->plan,
                'amount' => (float) $inv->amount,
                'currency' => $inv->currency,
                'billing_cycle' => $inv->billing_cycle,
                'gateway' => $inv->gateway,
                'paid_at' => $inv->paid_at?->toDateString(),
            ])
            ->toArray();
    }
}
