<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\GlAccount;
use App\Models\Tenant\GlJournalEntry;
use App\Models\Tenant\GlJournalLine;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\RegulatoryReport;
use App\Models\Tenant\RegulatoryReportConfig;
use App\Models\Tenant\User;
use App\Services\Mail\TenantMailService;
use App\Services\RegulatoryReportingService;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function regAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function regLoan(float $outstanding = 5000): Loan
{
    $type     = LoanType::factory()->create();
    $plan     = LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create();

    return Loan::factory()->create([
        'borrower_id'       => $borrower->id,
        'loan_type_id'      => $type->id,
        'loan_plan_id'      => $plan->id,
        'status'            => LoanStatus::Active,
        'outstanding_balance' => $outstanding,
        'principal_amount'  => $outstanding,
    ]);
}

function seedGlEquity(float $amount): void
{
    $account = GlAccount::create([
        'code'      => '3001',
        'name'      => 'Capital Fund',
        'type'      => 'equity',
        'is_active' => true,
    ]);

    $entry = GlJournalEntry::create([
        'reference'   => 'SEED-EQUITY',
        'description' => 'Test equity seed',
        'entry_date'  => now()->toDateString(),
        'posted_at'   => now(),
    ]);

    GlJournalLine::create([
        'journal_entry_id' => $entry->id,
        'account_id'       => $account->id,
        'side'             => 'credit',
        'amount'           => $amount,
    ]);
}

// ─── CAR Tests ────────────────────────────────────────────────────────────────

test('can generate a CAR report', function () {
    $admin = regAdmin();
    regLoan(10000);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.regulatory.generate'), [
            'report_type' => 'car',
            'period'      => '2026-03',
        ])
        ->assertCreated();

    expect($resp->json('data.report_type'))->toBe('car')
        ->and($resp->json('data.period'))->toBe('2026-03')
        ->and($resp->json('data.data'))->toHaveKey('car_pct');
});

test('CAR report shows compliant when capital exceeds threshold', function () {
    $admin = regAdmin();
    seedGlEquity(50000);  // large equity
    regLoan(10000);       // small loan portfolio

    $service = app(RegulatoryReportingService::class);
    $report  = $service->generate('car', '2026-03');

    // 50000 / 10000 = 500% CAR — well above 10% minimum
    expect($report->data['compliant'])->toBeTrue()
        ->and($report->data['car_pct'])->toBeGreaterThan(10.0);
});

test('CAR report shows non-compliant when capital is zero', function () {
    $admin = regAdmin();
    regLoan(10000); // no equity seeded

    $service = app(RegulatoryReportingService::class);
    $report  = $service->generate('car', '2026-03');

    expect($report->data['compliant'])->toBeFalse();
});

// ─── Liquidity Tests ──────────────────────────────────────────────────────────

test('can generate a liquidity ratio report', function () {
    $admin = regAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.regulatory.generate'), [
            'report_type' => 'liquidity',
            'period'      => '2026-03',
        ])
        ->assertCreated();

    expect($resp->json('data.data'))->toHaveKey('liquidity_ratio_pct')
        ->and($resp->json('data.data'))->toHaveKey('compliant');
});

// ─── Large Exposure Tests ─────────────────────────────────────────────────────

test('can generate a large exposure report', function () {
    $admin = regAdmin();
    seedGlEquity(10000); // small capital
    regLoan(8000);       // 80% of capital — breaches 25% limit

    $service = app(RegulatoryReportingService::class);
    $report  = $service->generate('large_exposure', '2026-03');

    expect($report->data['exposures'])->not->toBeEmpty();
    expect($report->data['exposures'][0]['breached'])->toBeTrue();
});

test('no large exposure when loans are within limit', function () {
    $admin = regAdmin();
    seedGlEquity(100000); // very large capital
    regLoan(5000);         // 5% — within limit

    $service = app(RegulatoryReportingService::class);
    $report  = $service->generate('large_exposure', '2026-03');

    expect($report->data['exposures'])->toBeEmpty();
});

// ─── PAR Tests ────────────────────────────────────────────────────────────────

test('can generate a PAR report', function () {
    $admin = regAdmin();
    regLoan(20000);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.regulatory.generate'), [
            'report_type' => 'par',
            'period'      => '2026-03',
        ])
        ->assertCreated();

    expect($resp->json('data.data'))->toHaveKey('total_portfolio')
        ->and($resp->json('data.data'))->toHaveKey('par_30')
        ->and($resp->json('data.data'))->toHaveKey('par_60')
        ->and($resp->json('data.data'))->toHaveKey('par_90');
});

// ─── Listing & retrieval ──────────────────────────────────────────────────────

test('can list generated reports', function () {
    $admin   = regAdmin();
    $service = app(RegulatoryReportingService::class);
    $service->generate('car', '2026-03');
    $service->generate('par', '2026-03');

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.regulatory.reports'))
        ->assertOk();

    expect(count($resp->json('data')))->toBeGreaterThanOrEqual(2);
});

test('can filter reports by type', function () {
    $admin   = regAdmin();
    $service = app(RegulatoryReportingService::class);
    $service->generate('car', '2026-03');
    $service->generate('par', '2026-03');

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.regulatory.reports') . '?report_type=car')
        ->assertOk();

    $types = collect($resp->json('data'))->pluck('report_type')->unique()->values()->toArray();
    expect($types)->toBe(['car']);
});

test('can retrieve a single report', function () {
    $admin   = regAdmin();
    $service = app(RegulatoryReportingService::class);
    $report  = $service->generate('liquidity', '2026-03');

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.regulatory.reports.show', $report))
        ->assertOk();

    expect($resp->json('data.report_type'))->toBe('liquidity');
});

// ─── Email delivery ───────────────────────────────────────────────────────────

test('can email a report to recipients', function () {
    $admin   = regAdmin();
    $service = app(RegulatoryReportingService::class);
    $report  = $service->generate('car', '2026-03');

    $mail = Mockery::mock(TenantMailService::class);
    $mail->shouldReceive('raw')->once();
    app()->instance(TenantMailService::class, $mail);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.regulatory.reports.email', $report), [
            'recipients' => ['compliance@bank.zm'],
        ])
        ->assertOk();

    expect($resp->json('data.emailed'))->toBeTrue();
    expect(RegulatoryReport::find($report->id)->emailed)->toBeTrue();
});

// ─── Report Config ────────────────────────────────────────────────────────────

test('can create a report schedule config', function () {
    $admin = regAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.regulatory.configs.upsert'), [
            'report_type'      => 'car',
            'name'             => 'Monthly CAR Report',
            'frequency'        => 'monthly',
            'recipient_emails' => 'cfo@bank.zm,compliance@bank.zm',
        ])
        ->assertCreated();

    expect($resp->json('data.report_type'))->toBe('car')
        ->and($resp->json('data.frequency'))->toBe('monthly');
});

test('can list report configs', function () {
    $admin = regAdmin();
    RegulatoryReportConfig::create([
        'report_type'      => 'par',
        'name'             => 'Quarterly PAR',
        'frequency'        => 'quarterly',
        'recipient_emails' => 'risk@bank.zm',
        'is_active'        => true,
    ]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.regulatory.configs'))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(1);
});

test('generate rejects invalid report type', function () {
    $admin = regAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.regulatory.generate'), [
            'report_type' => 'unknown_type',
            'period'      => '2026-03',
        ])
        ->assertUnprocessable();
});
