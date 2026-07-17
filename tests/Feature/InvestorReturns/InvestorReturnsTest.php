<?php

use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Investor;
use App\Models\Tenant\InvestorAllocation;
use App\Models\Tenant\InvestorDividend;
use App\Models\Tenant\Loan;
use App\Models\Tenant\User;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function returnsAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function returnsInvestor(array $attrs = []): Investor
{
    return Investor::create(array_merge([
        'investor_number' => 'INV-' . rand(10000, 99999),
        'name'            => 'Test Investor ' . rand(100, 999),
        'email'           => 'inv' . rand(100, 999) . '@test.com',
        'type'            => 'individual',
        'status'          => 'active',
    ], $attrs));
}

function withActiveAllocation(Investor $investor, float $amount = 10000): InvestorAllocation
{
    $borrower = Borrower::factory()->create();
    $loan     = Loan::factory()->create(['borrower_id' => $borrower->id, 'principal_amount' => $amount]);
    $staff    = User::factory()->create(['role' => \App\Enums\UserRole::SuperAdmin, 'is_active' => true]);

    return InvestorAllocation::create([
        'investor_id'      => $investor->id,
        'loan_id'          => $loan->id,
        'recorded_by'      => $staff->id,
        'allocated_amount' => $amount,
        'expected_return'  => $amount * 0.12,
        'actual_return'    => 0,
        'status'           => 'active',
        'allocation_date'  => now()->toDateString(),
    ]);
}

// ─── List Dividends ───────────────────────────────────────────────────────────

test('can list dividends and summary for an investor', function () {
    $admin    = returnsAdmin();
    $investor = returnsInvestor();
    withActiveAllocation($investor);

    InvestorDividend::create([
        'investor_id'    => $investor->id,
        'period'         => '2026-02',
        'principal'      => 10000,
        'return_rate'    => 12.0,
        'gross_dividend' => 100,
        'tax_withheld'   => 15,
        'net_dividend'   => 85,
        'status'         => 'paid',
    ]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.investors.dividends.index', $investor))
        ->assertOk();

    expect($resp->json('data.dividends'))->toHaveCount(1)
        ->and($resp->json('data.summary.total_paid'))->toBeGreaterThan(0);
});

// ─── Calculate Dividend ───────────────────────────────────────────────────────

test('can calculate a dividend for an investor', function () {
    $admin    = returnsAdmin();
    $investor = returnsInvestor();
    withActiveAllocation($investor, 12000);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.investors.dividends.calculate', $investor), [
            'period'          => '2026-03',
            'annual_rate_pct' => 12.0,
        ])
        ->assertCreated();

    // 12000 * 12% / 12 = 120 gross; 120 * 85% = 102 net
    expect((float) $resp->json('data.dividend.gross_dividend'))->toBe(120.0)
        ->and((float) $resp->json('data.dividend.net_dividend'))->toBe(102.0)
        ->and($resp->json('data.dividend.status'))->toBe('pending');
});

test('dividend calculation uses provided annual rate', function () {
    $admin    = returnsAdmin();
    $investor = returnsInvestor();
    withActiveAllocation($investor, 6000);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.investors.dividends.calculate', $investor), [
            'period'          => '2026-03',
            'annual_rate_pct' => 24.0,
        ])
        ->assertCreated();

    // 6000 * 24% / 12 = 120 gross
    expect((float) $resp->json('data.dividend.gross_dividend'))->toBe(120.0);
});

test('dividend calculation validates period format', function () {
    $admin    = returnsAdmin();
    $investor = returnsInvestor();

    $this->actingAs($admin)
        ->postJson(route('api.v1.investors.dividends.calculate', $investor), [
            'period' => '2026/03',   // wrong format
        ])
        ->assertUnprocessable();
});

// ─── Pay Dividend ─────────────────────────────────────────────────────────────

test('can mark a dividend as paid', function () {
    $admin    = returnsAdmin();
    $investor = returnsInvestor();
    withActiveAllocation($investor, 10000);

    $dividend = InvestorDividend::create([
        'investor_id'    => $investor->id,
        'period'         => '2026-03',
        'principal'      => 10000,
        'return_rate'    => 12,
        'gross_dividend' => 100,
        'tax_withheld'   => 15,
        'net_dividend'   => 85,
        'status'         => 'pending',
    ]);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.investor-dividends.pay', $dividend))
        ->assertOk();

    expect($resp->json('data.dividend.status'))->toBe('paid')
        ->and($resp->json('data.dividend.processed_by'))->toBe($admin->id);
});

test('cannot pay an already paid dividend', function () {
    $admin    = returnsAdmin();
    $investor = returnsInvestor();

    $dividend = InvestorDividend::create([
        'investor_id'    => $investor->id,
        'period'         => '2026-03',
        'principal'      => 10000,
        'return_rate'    => 12,
        'gross_dividend' => 100,
        'tax_withheld'   => 15,
        'net_dividend'   => 85,
        'status'         => 'paid',
    ]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.investor-dividends.pay', $dividend))
        ->assertUnprocessable();
});

// ─── Cancel Dividend ──────────────────────────────────────────────────────────

test('can cancel a pending dividend', function () {
    $admin    = returnsAdmin();
    $investor = returnsInvestor();

    $dividend = InvestorDividend::create([
        'investor_id'    => $investor->id,
        'period'         => '2026-03',
        'principal'      => 10000,
        'return_rate'    => 12,
        'gross_dividend' => 100,
        'tax_withheld'   => 15,
        'net_dividend'   => 85,
        'status'         => 'pending',
    ]);

    $resp = $this->actingAs($admin)
        ->deleteJson(route('api.v1.investor-dividends.cancel', $dividend))
        ->assertOk();

    expect($resp->json('data.dividend.status'))->toBe('cancelled');
});

test('cannot cancel a paid dividend', function () {
    $admin    = returnsAdmin();
    $investor = returnsInvestor();

    $dividend = InvestorDividend::create([
        'investor_id'    => $investor->id,
        'period'         => '2026-02',
        'principal'      => 10000,
        'return_rate'    => 12,
        'gross_dividend' => 100,
        'tax_withheld'   => 15,
        'net_dividend'   => 85,
        'status'         => 'paid',
    ]);

    $this->actingAs($admin)
        ->deleteJson(route('api.v1.investor-dividends.cancel', $dividend))
        ->assertUnprocessable();
});
