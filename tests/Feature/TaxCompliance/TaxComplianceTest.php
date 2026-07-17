<?php

use App\Enums\UserRole;
use App\Models\Tenant\Payment;
use App\Models\Tenant\TaxComputation;
use App\Models\Tenant\TaxConfiguration;
use App\Models\Tenant\User;
use App\Services\TaxComplianceService;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function taxAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function taxConfig(array $attrs = []): TaxConfiguration
{
    return TaxConfiguration::create(array_merge([
        'tax_type'            => 'wht',
        'rate'                => 15.0,
        'label'               => 'Withholding Tax',
        'applies_to_interest' => true,
        'applies_to_fees'     => false,
        'is_active'           => true,
    ], $attrs));
}

// ─── Tax Configuration CRUD ───────────────────────────────────────────────────

test('can create a tax configuration', function () {
    $admin = taxAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.tax.configurations.store'), [
            'tax_type'            => 'wht',
            'rate'                => 15.0,
            'label'               => 'WHT 15%',
            'applies_to_interest' => true,
        ])
        ->assertStatus(201);

    expect((float) $resp->json('data.configuration.rate'))->toBe(15.0);
    $this->assertDatabaseHas('tax_configurations', ['tax_type' => 'wht', 'is_active' => 1]);
});

test('can list tax configurations', function () {
    $admin = taxAdmin();
    taxConfig(['tax_type' => 'wht']);
    taxConfig(['tax_type' => 'vat', 'rate' => 16.0]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.tax.configurations'))
        ->assertOk();

    expect($resp->json('data.configurations'))->toHaveCount(2);
});

test('can update a tax configuration', function () {
    $admin  = taxAdmin();
    $config = taxConfig();

    $this->actingAs($admin)
        ->putJson(route('api.v1.tax.configurations.update', $config), ['rate' => 20.0])
        ->assertOk()
        ->assertJsonPath('data.configuration.rate', 20);
});

test('tax configuration requires valid tax_type', function () {
    $admin = taxAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.tax.configurations.store'), [
            'tax_type' => 'invalid',
            'rate'     => 10.0,
        ])
        ->assertJsonValidationErrors(['tax_type']);
});

// ─── WHT Computation ─────────────────────────────────────────────────────────

test('service computes WHT from interest_allocated on payment', function () {
    taxConfig(['rate' => 15.0]);

    $payment = Payment::factory()->create([
        'interest_allocated' => 1000.00,
        'payment_date'       => '2026-03-01',
    ]);

    $service     = app(TaxComplianceService::class);
    $computation = $service->computeWhtForPayment($payment);

    expect($computation)->not->toBeNull();
    expect($computation->tax_amount)->toBe(150.0);
    expect($computation->period)->toBe('2026-03');
    expect($computation->status)->toBe('computed');
});

test('WHT computation is idempotent — no duplicate on repeat call', function () {
    taxConfig(['rate' => 15.0]);

    $payment = Payment::factory()->create([
        'interest_allocated' => 500.00,
        'payment_date'       => '2026-03-01',
    ]);

    $service = app(TaxComplianceService::class);
    $service->computeWhtForPayment($payment);
    $service->computeWhtForPayment($payment);

    expect(TaxComputation::where('source_id', $payment->id)->count())->toBe(1);
});

test('no WHT computed when interest_allocated is zero', function () {
    taxConfig(['rate' => 15.0]);

    $payment = Payment::factory()->create([
        'interest_allocated' => 0.00,
        'payment_date'       => '2026-03-01',
    ]);

    $result = app(TaxComplianceService::class)->computeWhtForPayment($payment);
    expect($result)->toBeNull();
    expect(TaxComputation::count())->toBe(0);
});

test('no WHT computed when no active WHT config exists', function () {
    $payment = Payment::factory()->create([
        'interest_allocated' => 800.00,
        'payment_date'       => '2026-03-01',
    ]);

    $result = app(TaxComplianceService::class)->computeWhtForPayment($payment);
    expect($result)->toBeNull();
});

// ─── WHT Summary Report ───────────────────────────────────────────────────────

test('WHT summary returns grouped totals by period', function () {
    $admin  = taxAdmin();
    $config = taxConfig(['rate' => 15.0]);

    TaxComputation::create(['tax_configuration_id' => $config->id, 'source_type' => 'payment', 'source_id' => 1, 'taxable_amount' => 1000, 'tax_amount' => 150, 'period' => '2026-01', 'status' => 'computed']);
    TaxComputation::create(['tax_configuration_id' => $config->id, 'source_type' => 'payment', 'source_id' => 2, 'taxable_amount' => 2000, 'tax_amount' => 300, 'period' => '2026-02', 'status' => 'computed']);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.tax.wht-summary', ['from' => '2026-01', 'to' => '2026-03']))
        ->assertOk();

    expect($resp->json('data.summary'))->toHaveCount(2);
});

test('can mark a period as remitted', function () {
    $admin  = taxAdmin();
    $config = taxConfig();

    TaxComputation::create(['tax_configuration_id' => $config->id, 'source_type' => 'payment', 'source_id' => 1, 'taxable_amount' => 500, 'tax_amount' => 75, 'period' => '2026-03', 'status' => 'computed']);
    TaxComputation::create(['tax_configuration_id' => $config->id, 'source_type' => 'payment', 'source_id' => 2, 'taxable_amount' => 200, 'tax_amount' => 30, 'period' => '2026-03', 'status' => 'computed']);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.tax.wht-summary.remit', '2026-03'))
        ->assertOk();

    expect($resp->json('data.updated'))->toBe(2);
    expect(TaxComputation::where('period', '2026-03')->where('status', 'remitted')->count())->toBe(2);
});

// ─── PAR Report ───────────────────────────────────────────────────────────────

test('PAR report returns required structure', function () {
    $admin = taxAdmin();

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.tax.par-report'))
        ->assertOk();

    expect($resp->json('data'))->toHaveKeys(['total_portfolio', 'total_par', 'par_ratio', 'buckets']);
});

// ─── Capital Adequacy ─────────────────────────────────────────────────────────

test('capital adequacy returns structure', function () {
    $admin = taxAdmin();

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.tax.capital-adequacy'))
        ->assertOk();

    expect($resp->json('data'))->toHaveKeys(['total_loan_book', 'total_fund', 'exposure_ratio_pct']);
});

// ─── Computations list ────────────────────────────────────────────────────────

test('can list tax computations with filters', function () {
    $admin  = taxAdmin();
    $config = taxConfig();

    TaxComputation::create(['tax_configuration_id' => $config->id, 'source_type' => 'payment', 'source_id' => 1, 'taxable_amount' => 300, 'tax_amount' => 45, 'period' => '2026-03', 'status' => 'computed']);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.tax.computations', ['period' => '2026-03']))
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

// ─── Auth ─────────────────────────────────────────────────────────────────────

test('unauthenticated cannot access tax endpoints', function () {
    $this->getJson(route('api.v1.tax.configurations'))->assertStatus(401);
});
