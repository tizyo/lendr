<?php

use App\Models\Landlord\LandlordUser;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\SubscriptionInvoice;
use App\Models\Landlord\Tenant;

// ─── Cleanup ──────────────────────────────────────────────────────────────────
afterEach(function () {
    SubscriptionInvoice::query()->delete();
    Subscription::query()->delete();
    Tenant::query()->delete();
});

// ─── Helpers ──────────────────────────────────────────────────────────────────
function makeTenant(string $plan = 'starter', string $status = 'active', ?string $createdAt = null): Tenant
{
    $t = Tenant::create([
        'id'       => (string) \Illuminate\Support\Str::uuid(),
        'name'     => 'MFI ' . uniqid(),
        'slug'     => 'mfi-' . uniqid(),
        'plan'     => $plan,
        'status'   => $status,
        'currency' => 'ZMW',
        'timezone' => 'Africa/Lusaka',
    ]);

    if ($createdAt) {
        $t->timestamps = false;
        $t->forceFill(['created_at' => $createdAt])->save();
        $t->timestamps = true;
    }

    return $t->refresh();
}

function makeSubscription(Tenant $tenant, string $plan = 'growth', string $billing = 'monthly', float $amount = 1499.00): Subscription
{
    return Subscription::create([
        'tenant_id'     => $tenant->id,
        'plan'          => $plan,
        'status'        => 'active',
        'gateway'       => 'flutterwave',
        'amount'        => $amount,
        'currency'      => 'ZMW',
        'billing_cycle' => $billing,
        'starts_at'     => now()->subMonth(),
        'ends_at'       => now()->addMonth(),
    ]);
}

function makePaidInvoice(Tenant $tenant, string $plan = 'growth', float $amount = 1499.00, ?string $paidAt = null): SubscriptionInvoice
{
    return SubscriptionInvoice::create([
        'tenant_id'      => $tenant->id,
        'gateway'        => 'flutterwave',
        'gateway_tx_ref' => 'LENDR-SUB-' . uniqid(),
        'plan'           => $plan,
        'amount'         => $amount,
        'currency'       => 'ZMW',
        'billing_cycle'  => 'monthly',
        'status'         => 'paid',
        'paid_at'        => $paidAt ?? now()->toDateTimeString(),
    ]);
}

// ─── Basic response shape ─────────────────────────────────────────────────────

it('returns the full stats payload', function () {
    $landlord = LandlordUser::factory()->create();

    $response = $this->actingAs($landlord, 'sanctum')
        ->getJson('/api/v1/landlord/stats');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'tenants'  => ['total', 'by_plan', 'by_status', 'new_this_month', 'trial_conversion_rate', 'monthly_churn_rate'],
                'revenue'  => ['mrr', 'arr', 'total_revenue', 'trend', 'by_plan'],
                'growth'   => ['signup_trend'],
                'recent_invoices',
            ],
        ]);
});

// ─── MRR / ARR ────────────────────────────────────────────────────────────────

it('calculates MRR from active monthly subscriptions', function () {
    $t1 = makeTenant('growth', 'active');
    $t2 = makeTenant('growth', 'active');
    makeSubscription($t1, 'growth', 'monthly', 1499.00);
    makeSubscription($t2, 'growth', 'monthly', 1499.00);

    $landlord = LandlordUser::factory()->create();

    $data = $this->actingAs($landlord, 'sanctum')
        ->getJson('/api/v1/landlord/stats')
        ->assertOk()
        ->json('data.revenue');

    expect((float) $data['mrr'])->toBe(2998.0);
    expect((float) $data['arr'])->toBe(2998.0 * 12);
});

it('normalises annual subscriptions to monthly for MRR', function () {
    $t = makeTenant('enterprise', 'active');
    makeSubscription($t, 'enterprise', 'annual', 12000.00); // 1000/month normalised

    $landlord = LandlordUser::factory()->create();

    $data = $this->actingAs($landlord, 'sanctum')
        ->getJson('/api/v1/landlord/stats')
        ->assertOk()
        ->json('data.revenue');

    expect((float) $data['mrr'])->toBe(1000.0);
});

it('excludes expired subscriptions from MRR', function () {
    $t = makeTenant('growth', 'active');
    Subscription::create([
        'tenant_id'     => $t->id,
        'plan'          => 'growth',
        'status'        => 'active',
        'gateway'       => 'flutterwave',
        'amount'        => 1499.00,
        'currency'      => 'ZMW',
        'billing_cycle' => 'monthly',
        'starts_at'     => now()->subMonths(3),
        'ends_at'       => now()->subDay(), // expired yesterday
    ]);

    $landlord = LandlordUser::factory()->create();

    $data = $this->actingAs($landlord, 'sanctum')
        ->getJson('/api/v1/landlord/stats')
        ->assertOk()
        ->json('data.revenue');

    expect((float) $data['mrr'])->toBe(0.0);
});

// ─── Total revenue ────────────────────────────────────────────────────────────

it('sums all-time paid invoice amounts for total revenue', function () {
    $t = makeTenant();
    makePaidInvoice($t, 'growth', 1499.00);
    makePaidInvoice($t, 'growth', 1499.00);

    $landlord = LandlordUser::factory()->create();

    $data = $this->actingAs($landlord, 'sanctum')
        ->getJson('/api/v1/landlord/stats')
        ->assertOk()
        ->json('data.revenue');

    expect((float) $data['total_revenue'])->toBe(2998.0);
});

// ─── Revenue trend ────────────────────────────────────────────────────────────

it('groups paid invoices by month for revenue trend', function () {
    $t = makeTenant();
    $thisMonth = now()->format('Y-m');
    makePaidInvoice($t, 'growth', 1499.00, now()->toDateTimeString());
    makePaidInvoice($t, 'growth', 1499.00, now()->toDateTimeString());

    $landlord = LandlordUser::factory()->create();

    $trend = $this->actingAs($landlord, 'sanctum')
        ->getJson('/api/v1/landlord/stats')
        ->assertOk()
        ->json('data.revenue.trend');

    expect($trend)->toHaveKey($thisMonth);
    expect((float) $trend[$thisMonth])->toBe(2998.0);
});

// ─── Trial conversion rate ────────────────────────────────────────────────────

it('calculates trial conversion rate from 90-day cohort', function () {
    // 2 active (converted), 2 still on trial — out of 4 in 90 days => 50%
    makeTenant('growth',  'active');
    makeTenant('starter', 'active');
    makeTenant('starter', 'trial');
    makeTenant('starter', 'trial');

    $landlord = LandlordUser::factory()->create();

    $rate = $this->actingAs($landlord, 'sanctum')
        ->getJson('/api/v1/landlord/stats')
        ->assertOk()
        ->json('data.tenants.trial_conversion_rate');

    expect((float) $rate)->toBe(50.0);
});

it('returns zero conversion rate when cohort is empty', function () {
    $landlord = LandlordUser::factory()->create();

    $rate = $this->actingAs($landlord, 'sanctum')
        ->getJson('/api/v1/landlord/stats')
        ->assertOk()
        ->json('data.tenants.trial_conversion_rate');

    expect((float) $rate)->toBe(0.0);
});

// ─── Monthly churn rate ───────────────────────────────────────────────────────

it('calculates monthly churn rate', function () {
    // 1 old tenant (created before month) + 1 that churned this month
    $old = makeTenant('starter', 'active', now()->subMonths(2)->toDateTimeString());
    $churned = makeTenant('starter', 'cancelled', now()->subMonths(2)->toDateTimeString());
    $churned->forceFill(['updated_at' => now()->startOfMonth()->addDay()])->save();

    $landlord = LandlordUser::factory()->create();

    $rate = $this->actingAs($landlord, 'sanctum')
        ->getJson('/api/v1/landlord/stats')
        ->assertOk()
        ->json('data.tenants.monthly_churn_rate');

    // 1 churned / 2 pre-month tenants = 50%
    expect((float) $rate)->toBe(50.0);
});

// ─── Recent invoices ──────────────────────────────────────────────────────────

it('returns up to 5 recent paid invoices', function () {
    $t = makeTenant();
    for ($i = 0; $i < 7; $i++) {
        makePaidInvoice($t);
    }

    $landlord = LandlordUser::factory()->create();

    $invoices = $this->actingAs($landlord, 'sanctum')
        ->getJson('/api/v1/landlord/stats')
        ->assertOk()
        ->json('data.recent_invoices');

    expect(count($invoices))->toBeLessThanOrEqual(5);
    expect($invoices[0])->toHaveKeys(['id', 'tenant_name', 'plan', 'amount', 'currency', 'paid_at']);
});

// ─── Signup trend ─────────────────────────────────────────────────────────────

it('groups new tenants by month for signup trend', function () {
    $thisMonth = now()->format('Y-m');
    makeTenant();
    makeTenant();

    $landlord = LandlordUser::factory()->create();

    $trend = $this->actingAs($landlord, 'sanctum')
        ->getJson('/api/v1/landlord/stats')
        ->assertOk()
        ->json('data.growth.signup_trend');

    expect($trend)->toHaveKey($thisMonth);
    expect((int) $trend[$thisMonth])->toBeGreaterThanOrEqual(2);
});

// ─── Plan distribution ────────────────────────────────────────────────────────

it('breaks down tenant counts by plan', function () {
    makeTenant('starter', 'active');
    makeTenant('starter', 'active');
    makeTenant('growth',  'active');

    $landlord = LandlordUser::factory()->create();

    $byPlan = $this->actingAs($landlord, 'sanctum')
        ->getJson('/api/v1/landlord/stats')
        ->assertOk()
        ->json('data.tenants.by_plan');

    expect((int) $byPlan['starter'])->toBe(2);
    expect((int) $byPlan['growth'])->toBe(1);
});
