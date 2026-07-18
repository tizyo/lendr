<?php

use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\MobileMoneyIntent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function gatewayLoan(): Loan
{
    $borrower = Borrower::factory()->create(['is_active' => true]);
    $lt = LoanType::factory()->create();
    $plan = LoanPlan::factory()->create(['loan_type_id' => $lt->id]);

    return Loan::factory()->active()->create([
        'borrower_id' => $borrower->id,
        'loan_type_id' => $lt->id,
        'loan_plan_id' => $plan->id,
        'principal_amount' => 5000,
        'interest_amount' => 750,
        'total_payable' => 5750,
        'outstanding_balance' => 5750,
        'total_paid' => 0,
        'penalty_balance' => 0,
    ]);
}

function gatewayIntent(Loan $loan, float $amount, string $ref, string $provider = 'flutterwave'): MobileMoneyIntent
{
    return MobileMoneyIntent::create([
        'loan_id' => $loan->id,
        'borrower_id' => $loan->borrower_id,
        'reference' => $ref,
        'amount' => $amount,
        'provider' => $provider,
        'phone' => '+260971000099',
        'status' => 'pending',
    ]);
}

const GATEWAY_TEST_SECRET = 'test-secret';

function webhookSecret(string $key, string $value = GATEWAY_TEST_SECRET): void
{
    DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value]);
}

// ─── Flutterwave ─────────────────────────────────────────────────────────────

test('flutterwave gateway: successful charge records payment', function () {
    $loan = gatewayLoan();
    $ref = 'FW-'.uniqid();
    gatewayIntent($loan, 1000, $ref);
    webhookSecret('flutterwave_webhook_secret');

    $this->postJson(route('api.v1.webhooks.flutterwave'), [
        'event' => 'charge.completed',
        'data' => [
            'id' => 'fw-gw-001',
            'status' => 'successful',
            'amount' => 1000,
            'tx_ref' => $ref,
            'customer' => ['phone_number' => '+260971000099'],
        ],
    ], ['verif-hash' => GATEWAY_TEST_SECRET])->assertStatus(204);

    expect(\App\Models\Tenant\Payment::where('loan_id', $loan->id)->count())->toBe(1);
});

test('flutterwave gateway: failed charge does not record payment', function () {
    $loan = gatewayLoan();
    $ref = 'FW-'.uniqid();
    gatewayIntent($loan, 500, $ref);
    webhookSecret('flutterwave_webhook_secret');

    $this->postJson(route('api.v1.webhooks.flutterwave'), [
        'event' => 'charge.completed',
        'data' => [
            'id' => 'fw-gw-fail-001',
            'status' => 'failed',
            'amount' => 500,
            'tx_ref' => $ref,
            'customer' => ['phone_number' => '+260971000099'],
        ],
    ], ['verif-hash' => GATEWAY_TEST_SECRET])->assertStatus(204);

    expect(\App\Models\Tenant\Payment::where('loan_id', $loan->id)->count())->toBe(0);
});

test('flutterwave gateway: duplicate event is idempotent', function () {
    $loan = gatewayLoan();
    $ref = 'FW-'.uniqid();
    $txId = 'fw-gw-idem-001';
    gatewayIntent($loan, 750, $ref);
    webhookSecret('flutterwave_webhook_secret');

    $payload = [
        'event' => 'charge.completed',
        'data' => ['id' => $txId, 'status' => 'successful', 'amount' => 750, 'tx_ref' => $ref, 'customer' => ['phone_number' => '+260971000099']],
    ];
    $headers = ['verif-hash' => GATEWAY_TEST_SECRET];

    $this->postJson(route('api.v1.webhooks.flutterwave'), $payload, $headers)->assertStatus(204);
    $this->postJson(route('api.v1.webhooks.flutterwave'), $payload, $headers)->assertStatus(204);

    expect(\App\Models\Tenant\Payment::where('loan_id', $loan->id)->count())->toBe(1);
});

test('flutterwave gateway: wrong signature returns 401', function () {
    webhookSecret('flutterwave_webhook_secret', 'secret123');

    $this->postJson(
        route('api.v1.webhooks.flutterwave'),
        ['event' => 'charge.completed', 'data' => []],
        ['verif-hash' => 'wrong-secret'],
    )->assertStatus(401);
});

// ─── PawaPay ─────────────────────────────────────────────────────────────────

function pawapaySigned(array $payload): array
{
    return ['X-PawaPay-Signature' => hash_hmac('sha256', json_encode($payload), GATEWAY_TEST_SECRET)];
}

test('pawapay gateway: COMPLETED status records payment', function () {
    $loan = gatewayLoan();
    $ref = 'PP-'.uniqid();
    gatewayIntent($loan, 2000, $ref, 'pawapay');
    webhookSecret('pawapay_webhook_secret');

    $payload = [
        'paymentId' => 'pp-gw-001',
        'status' => 'COMPLETED',
        'amount' => '2000',
        'statementDescription' => $ref,
        'payer' => ['address' => ['value' => '260971000099']],
    ];

    $this->postJson(route('api.v1.webhooks.pawapay'), $payload, pawapaySigned($payload))->assertStatus(204);

    expect(\App\Models\Tenant\Payment::where('loan_id', $loan->id)->count())->toBe(1);
});

test('pawapay gateway: FAILED status marks intent failed and no payment', function () {
    $loan = gatewayLoan();
    $ref = 'PP-'.uniqid();
    $intent = gatewayIntent($loan, 500, $ref, 'pawapay');
    webhookSecret('pawapay_webhook_secret');

    $payload = [
        'paymentId' => 'pp-gw-fail-001',
        'status' => 'FAILED',
        'amount' => '500',
        'statementDescription' => $ref,
        'payer' => ['address' => ['value' => '260971000099']],
    ];

    $this->postJson(route('api.v1.webhooks.pawapay'), $payload, pawapaySigned($payload))->assertStatus(204);

    expect(\App\Models\Tenant\Payment::where('loan_id', $loan->id)->count())->toBe(0)
        ->and($intent->fresh()->status)->toBe('failed');
});

test('pawapay gateway: unknown ref returns 204 and no payment', function () {
    webhookSecret('pawapay_webhook_secret');

    $payload = [
        'paymentId' => 'pp-unknown',
        'status' => 'COMPLETED',
        'amount' => '100',
        'statementDescription' => 'UNKNOWN-REF-'.uniqid(),
        'payer' => ['address' => ['value' => '260971000099']],
    ];

    $this->postJson(route('api.v1.webhooks.pawapay'), $payload, pawapaySigned($payload))->assertStatus(204);

    expect(\App\Models\Tenant\Payment::count())->toBe(0);
});

// ─── SMS gateway mocked HTTP ──────────────────────────────────────────────────

test('sms gateway: smsto driver sends successfully via mocked http', function () {
    Http::fake([
        'https://api.sms.to/sms/send' => Http::response(['success' => true, 'message_id' => 'test-001'], 200),
    ]);

    $driver = new \App\Services\SMS\Drivers\SmsToDriver(apiKey: 'test-key', senderId: 'LENDR');
    expect($driver->send('+260971000001', 'Test'))->toBeTrue();
});

test('sms gateway: smsto driver returns false on http failure', function () {
    Http::fake([
        'https://api.sms.to/sms/send' => Http::response([], 500),
    ]);

    $driver = new \App\Services\SMS\Drivers\SmsToDriver(apiKey: 'test-key', senderId: 'LENDR');
    expect($driver->send('+260971000001', 'Test'))->toBeFalse();
});
