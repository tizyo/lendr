<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\ExchangeRate;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;
use App\Services\MultiCurrencyService;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function fxAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function fxLoan(string $currency = 'USD', float $outstanding = 5000): Loan
{
    $type = LoanType::factory()->create();
    $plan = LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create();

    return Loan::factory()->create([
        'borrower_id' => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'status' => LoanStatus::Active,
        'currency' => $currency,
        'base_currency' => 'ZMW',
        'outstanding_balance' => $outstanding,
    ]);
}

function seedRate(string $from, string $to, float $rate): ExchangeRate
{
    return ExchangeRate::create([
        'from_currency' => $from,
        'to_currency' => $to,
        'rate' => $rate,
        'effective_date' => now()->toDateString(),
    ]);
}

// ─── Service unit tests ───────────────────────────────────────────────────────

test('converts amount using exchange rate', function () {
    seedRate('USD', 'ZMW', 27.5);
    $service = app(MultiCurrencyService::class);

    $converted = $service->convert(100.0, 'USD', 'ZMW');
    expect($converted)->toBe(2750.0);
});

test('returns amount unchanged when same currency', function () {
    $service = app(MultiCurrencyService::class);
    expect($service->convert(100.0, 'ZMW', 'ZMW'))->toBe(100.0);
});

test('falls back to inverse rate when direct rate missing', function () {
    // 1 ZMW = 0.036 USD → inverse: 1 USD = 1/0.036 ≈ 27.78 ZMW
    seedRate('ZMW', 'USD', 0.036);
    $service = app(MultiCurrencyService::class);

    $rate = $service->rateFor('USD', 'ZMW');
    expect($rate)->toBeGreaterThan(1.0); // ~27.78
});

test('returns rate 1 when no exchange rate found', function () {
    $service = app(MultiCurrencyService::class);
    $rate = $service->rateFor('GBP', 'ZMW');
    expect($rate)->toBe(1.0);
});

test('lockRateForLoan stores fx_rate on loan', function () {
    seedRate('USD', 'ZMW', 26.0);
    $loan = fxLoan('USD');
    $service = app(MultiCurrencyService::class);

    $service->lockRateForLoan($loan);
    $loan->refresh();

    expect((float) $loan->fx_rate)->toBe(26.0);
});

test('lockRateForLoan sets fx_rate to 1 for same currency', function () {
    $type = LoanType::factory()->create();
    $plan = LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create();
    $loan = Loan::factory()->create([
        'borrower_id' => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'status' => LoanStatus::Active,
        'currency' => 'ZMW',
        'base_currency' => 'ZMW',
    ]);

    $service = app(MultiCurrencyService::class);
    $service->lockRateForLoan($loan);

    expect((float) $loan->fresh()->fx_rate)->toBe(1.0);
});

test('outstandingInBase converts using stored fx_rate', function () {
    $loan = fxLoan('USD', 1000);
    $loan->update(['fx_rate' => 27.5]);

    $service = app(MultiCurrencyService::class);
    expect($service->outstandingInBase($loan))->toBe(27500.0);
});

// ─── API tests ────────────────────────────────────────────────────────────────

test('can convert currency via API', function () {
    $admin = fxAdmin();
    seedRate('USD', 'ZMW', 27.5);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.multi-currency.convert'), [
            'amount' => 100,
            'from' => 'USD',
            'to' => 'ZMW',
        ])
        ->assertOk();

    expect((float) $resp->json('data.converted'))->toBe(2750.0)
        ->and((float) $resp->json('data.rate'))->toBe(27.5);
});

test('convert validates required fields', function () {
    $admin = fxAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.multi-currency.convert'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount', 'from', 'to']);
});

test('can get loan currency info', function () {
    $admin = fxAdmin();
    seedRate('USD', 'ZMW', 27.0);
    $loan = fxLoan('USD', 2000);
    $loan->update(['fx_rate' => 27.0]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.loans.currency', $loan))
        ->assertOk();

    expect($resp->json('data.currency'))->toBe('USD')
        ->and($resp->json('data.base_currency'))->toBe('ZMW')
        ->and((float) $resp->json('data.outstanding_base'))->toBe(54000.0);
});

test('portfolio summary groups loans by currency', function () {
    $admin = fxAdmin();
    seedRate('USD', 'ZMW', 27.0);
    fxLoan('USD', 1000);
    fxLoan('ZMW', 5000);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.multi-currency.portfolio'))
        ->assertOk();

    expect($resp->json('data.base_currency'))->toBe('ZMW')
        ->and($resp->json('data.by_currency'))->toHaveKey('ZMW');
});
