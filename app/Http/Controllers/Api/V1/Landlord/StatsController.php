<?php

namespace App\Http\Controllers\Api\V1\Landlord;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\SubscriptionInvoice;
use App\Models\Landlord\Tenant;
use Illuminate\Http\JsonResponse;

class StatsController extends BaseApiController
{
    /**
     * GET /v1/landlord/stats — platform-level KPIs for the landlord dashboard.
     */
    public function index(): JsonResponse
    {
        $tenants = Tenant::all();

        return $this->success([
            'tenants'          => $this->tenantStats($tenants),
            'revenue'          => $this->revenueStats(),
            'growth'           => $this->growthStats($tenants),
            'recent_invoices'  => $this->recentInvoices(),
        ]);
    }

    // ─── Tenant counts & distribution ─────────────────────────────────────────

    private function tenantStats($tenants): array
    {
        $byPlan   = $tenants->groupBy('plan')->map->count();
        $byStatus = $tenants->groupBy('status')->map->count();
        $total    = $tenants->count();

        // Trial conversion rate — 90-day cohort
        $cohortCutoff     = now()->subDays(90);
        $cohortTenants    = $tenants->where('created_at', '>=', $cohortCutoff);
        $cohortTotal      = $cohortTenants->count();
        $cohortConverted  = $cohortTenants->where('status', 'active')->count();
        $conversionRate   = $cohortTotal > 0
            ? round($cohortConverted / $cohortTotal * 100, 1)
            : 0.0;

        // Monthly churn rate — tenants that churned (expired/cancelled) this calendar month
        $monthStart      = now()->startOfMonth();
        $churned         = $tenants
            ->whereIn('status', ['expired', 'cancelled'])
            ->where('updated_at', '>=', $monthStart)
            ->count();
        $activeAtMonthStart = $tenants->where('created_at', '<', $monthStart)->count();
        $churnRate       = $activeAtMonthStart > 0
            ? round($churned / $activeAtMonthStart * 100, 1)
            : 0.0;

        return [
            'total'                => $total,
            'by_plan'              => $byPlan,
            'by_status'            => $byStatus,
            'new_this_month'       => $tenants
                ->where('created_at', '>=', $monthStart)
                ->count(),
            'trial_conversion_rate' => $conversionRate,   // % — 90-day cohort
            'monthly_churn_rate'    => $churnRate,         // % — this month
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
        $invoices  = SubscriptionInvoice::where('status', 'paid')
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
            'mrr'           => round($mrr, 2),
            'arr'           => round($arr, 2),
            'total_revenue' => round((float) $totalRevenue, 2),
            'trend'         => $trend,          // { "2026-01": 2998.00, ... }
            'by_plan'       => $byPlan,         // { "growth": 1499.00, ... }
        ];
    }

    // ─── Growth metrics ────────────────────────────────────────────────────────

    private function growthStats($tenants): array
    {
        // New tenant signups by month — last 6 months
        $from  = now()->subMonths(5)->startOfMonth();
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
                'id'            => $inv->id,
                'tenant_name'   => $inv->tenant?->name,
                'plan'          => $inv->plan,
                'amount'        => (float) $inv->amount,
                'currency'      => $inv->currency,
                'billing_cycle' => $inv->billing_cycle,
                'gateway'       => $inv->gateway,
                'paid_at'       => $inv->paid_at?->toDateString(),
            ])
            ->toArray();
    }
}
