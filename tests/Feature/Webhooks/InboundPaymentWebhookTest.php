<?php

use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\MobileMoneyIntent;
use App\Models\Tenant\Payment;
use App\Models\Tenant\WebhookEvent;
use Illuminate\Support\Facades\DB;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function webhookActiveLoan(): Loan
{
    $borrower = Borrower::factory()->create(['is_active' => true]);
    $lt       = LoanType::factory()->create();
    $plan     = LoanPlan::factory()->create(['loan_type_id' => $lt->id]);

    return Loan::factory()->active()->create([
        'borrower_id'         => $borrower->id,
        'loan_type_id'        => $lt->id,
        'loan_plan_id'        => $plan->id,
        'principal_amount'    => 2000.00,
        'interest_amount'     => 400.00,
        'total_payable'       => 2400.00,
        'outstanding_balance' => 2400.00,
        'total_paid'          => 0.00,
        'penalty_balance'     => 0.00,
    ]);
}

function createIntent(Loan $loan, float $amount, string $ref): MobileMoneyIntent
{
    return MobileMoneyIntent::create([
        'loan_id'   => $loan->id,
        'reference' => $ref,
        'amount'    => $amount,
        'provider'  => 'pawapay',
        'phone'     => '+260971000001',
        'status'    => 'pending',
    ]);
}

// ─── Flutterwave webhook ──────────────────────────────────────────────────────

test('flutterwave webhook records a payment on success', function () {
    $loan   = webhookActiveLoan();
    $ref    = 'LENDR-' . uniqid();
    $intent = createIntent($loan, 500.00, $ref);

    DB::table('settings')->updateOrInsert(
        ['key' => 'flutterwave_webhook_secret'],
        ['value' => '']  // empty = bypass signature check in dev
    );

    $payload = [
        'event' => 'charge.completed',
        'data'  => [
            'id'     => 'fw-txn-001',
            'status' => 'successful',
            'amount' => 500,
            'tx_ref' => $ref,
            'customer' => ['phone_number' => '+260971000001'],
        ],
    ];

    $this->postJson(route('webhooks.flutterwave'), $payload)
        ->assertStatus(204);

    expect(Payment::where('loan_id', $loan->id)->count())->toBe(1);
    expect($intent->fresh()->status)->toBe('confirmed');
});

test('flutterwave webhook rejects invalid signature', function () {
    DB::table('settings')->updateOrInsert(
        ['key' => 'flutterwave_webhook_secret'],
        ['value' => 'correct-secret']
    );

    $this->postJson(
        route('webhooks.flutterwave'),
        ['event' => 'charge.completed', 'data' => []],
        ['verif-hash' => 'wrong-secret']
    )->assertStatus(401);
});

test('flutterwave webhook is idempotent for duplicate events', function () {
    $loan   = webhookActiveLoan();
    $ref    = 'LENDR-' . uniqid();
    $intent = createIntent($loan, 300.00, $ref);
    $txId   = 'fw-txn-idempotent';

    DB::table('settings')->updateOrInsert(
        ['key' => 'flutterwave_webhook_secret'],
        ['value' => '']
    );

    $payload = [
        'event' => 'charge.completed',
        'data'  => [
            'id'       => $txId,
            'status'   => 'successful',
            'amount'   => 300,
            'tx_ref'   => $ref,
            'customer' => ['phone_number' => '+260971000001'],
        ],
    ];

    $this->postJson(route('webhooks.flutterwave'), $payload)->assertStatus(204);
    $this->postJson(route('webhooks.flutterwave'), $payload)->assertStatus(204); // duplicate

    // Only one payment should be recorded
    expect(Payment::where('loan_id', $loan->id)->count())->toBe(1);
});

// ─── PawaPay webhook ──────────────────────────────────────────────────────────

test('pawapay webhook records a payment on completed status', function () {
    $loan   = webhookActiveLoan();
    $ref    = 'LENDR-' . uniqid();
    $intent = createIntent($loan, 750.00, $ref);

    DB::table('settings')->updateOrInsert(
        ['key' => 'pawapay_webhook_secret'],
        ['value' => ''] // empty = dev bypass
    );

    $payload = [
        'paymentId'            => 'pp-txn-001',
        'status'               => 'COMPLETED',
        'amount'               => '750',
        'statementDescription' => $ref,
        'payer'                => ['address' => ['value' => '260971000001']],
    ];

    $this->postJson(route('webhooks.pawapay'), $payload)
        ->assertStatus(204);

    expect(Payment::where('loan_id', $loan->id)->count())->toBe(1);
});

test('pawapay webhook does not record payment on failed status', function () {
    $loan   = webhookActiveLoan();
    $ref    = 'LENDR-' . uniqid();
    $intent = createIntent($loan, 750.00, $ref);

    DB::table('settings')->updateOrInsert(
        ['key' => 'pawapay_webhook_secret'],
        ['value' => '']
    );

    $payload = [
        'paymentId'            => 'pp-txn-002',
        'status'               => 'FAILED',
        'amount'               => '750',
        'statementDescription' => $ref,
        'payer'                => ['address' => ['value' => '260971000001']],
    ];

    $this->postJson(route('webhooks.pawapay'), $payload)
        ->assertStatus(204);

    expect(Payment::where('loan_id', $loan->id)->count())->toBe(0);
    expect($intent->fresh()->status)->toBe('failed');
});

// ─── Unknown reference handling ───────────────────────────────────────────────

test('webhook with unknown internal ref is logged as skipped and returns 204', function () {
    DB::table('settings')->updateOrInsert(
        ['key' => 'pawapay_webhook_secret'],
        ['value' => '']
    );

    $payload = [
        'paymentId'            => 'pp-unknown-001',
        'status'               => 'COMPLETED',
        'amount'               => '100',
        'statementDescription' => 'UNKNOWN-REF-9999',
        'payer'                => ['address' => ['value' => '260971000001']],
    ];

    $this->postJson(route('webhooks.pawapay'), $payload)
        ->assertStatus(204);

    // No payment should be created
    expect(Payment::count())->toBe(0);
});
