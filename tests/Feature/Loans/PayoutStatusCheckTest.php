<?php

use App\Enums\LoanStatus;
use App\Models\Tenant\BorrowerNotification;
use App\Models\Tenant\DisbursementLog;
use App\Models\Tenant\InAppNotification;
use App\Models\Tenant\Loan;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

// ─── Helpers ─────────────────────────────────────────────────────────────────

beforeEach(function () {
    actingAsAdmin(); // seeds roles so staff alerts have recipients
    foreach ([
        'pawapay_api_key' => 'pp-key',
        'airtel_client_id' => 'id', 'airtel_client_secret' => 'secret',
        'mtn_disbursement_subscription_key' => 'sub', 'mtn_api_user' => 'user', 'mtn_api_key' => 'key',
    ] as $k => $v) {
        DB::table('settings')->updateOrInsert(['key' => $k], ['value' => $v, 'updated_at' => now()]);
    }
});

/** An accepted payout, as DisbursementOutcomeService::accepted() leaves it. */
function acceptedPayout(string $gateway, int $ageMinutes = 30, ?string $providerRef = null): DisbursementLog
{
    $loan = Loan::factory()->create(['status' => LoanStatus::Active, 'disbursement_method' => $gateway, 'principal_amount' => 5000]);

    $log = DisbursementLog::create([
        'loan_id' => $loan->id,
        'gateway' => $gateway,
        'reference' => 'LENDR-DISB-'.$loan->id.'-abc',
        'provider_reference' => $providerRef ?? (string) Str::uuid(),
        'amount' => 5000,
        'status' => 'processing',
        'used_wallet' => false,
    ]);

    DB::table('disbursement_logs')->where('id', $log->id)->update([
        'created_at' => now()->subMinutes($ageMinutes),
        'updated_at' => now()->subMinutes($ageMinutes),
    ]);

    return $log->fresh();
}

function checkPayouts(): void
{
    test()->artisan('lendr:check-payouts')->assertSuccessful();
}

// ─── Completed ───────────────────────────────────────────────────────────────

it('marks a payout completed when PawaPay confirms it', function () {
    $log = acceptedPayout('pawapay');
    Http::fake(['*/payouts/*' => Http::response([['payoutId' => $log->provider_reference, 'status' => 'COMPLETED']], 200)]);

    checkPayouts();

    expect($log->fresh()->status)->toBe('completed')
        ->and($log->loan->fresh()->status)->toBe(LoanStatus::Active);
});

it('marks a payout completed when MTN reports SUCCESSFUL', function () {
    $log = acceptedPayout('mtn_momo');
    Http::fake([
        '*/disbursement/token/' => Http::response(['access_token' => 'tok'], 200),
        '*/disbursement/v1_0/transfer/*' => Http::response(['status' => 'SUCCESSFUL'], 200),
    ]);

    checkPayouts();

    expect($log->fresh()->status)->toBe('completed');
});

// ─── Failed after acceptance ─────────────────────────────────────────────────

it('reverses the disbursement and tells everyone when PawaPay reports FAILED', function () {
    $log = acceptedPayout('pawapay');
    Http::fake(['*/payouts/*' => Http::response([[
        'status' => 'FAILED',
        'failureReason' => ['failureCode' => 'RECIPIENT_NOT_FOUND', 'failureMessage' => 'Wallet does not exist'],
    ]], 200)]);

    checkPayouts();

    $log->refresh();

    expect($log->status)->toBe('reversed')
        ->and($log->failure_reason)->toContain('Wallet does not exist')
        ->and($log->loan->fresh()->status)->toBe(LoanStatus::Approved)
        ->and(InAppNotification::where('type', 'disbursement_failed')->exists())->toBeTrue()
        ->and(BorrowerNotification::where('borrower_id', $log->loan->borrower_id)->where('type', 'disbursement_failed')->exists())->toBeTrue();
});

it('reverses when Airtel reports the transfer failed (TF), looking it up by our reference', function () {
    $log = acceptedPayout('airtel_money');
    Http::fake([
        '*/auth/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
        '*/standard/v1/disbursements/*' => Http::response(['data' => ['transaction' => ['status' => 'TF', 'message' => 'Payee not registered']]], 200),
    ]);

    checkPayouts();

    expect($log->fresh()->status)->toBe('reversed')
        ->and($log->loan->fresh()->status)->toBe(LoanStatus::Approved);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), "/disbursements/{$log->reference}"));
});

// ─── Still pending / uncheckable ─────────────────────────────────────────────

it('leaves a recent pending payout alone', function () {
    $log = acceptedPayout('pawapay');
    Http::fake(['*/payouts/*' => Http::response([['status' => 'SUBMITTED']], 200)]);

    checkPayouts();

    expect($log->fresh()->status)->toBe('processing');
});

it('hands a payout to staff when it is still unresolved after 48 hours', function () {
    $log = acceptedPayout('pawapay', ageMinutes: 49 * 60);
    Http::fake(['*/payouts/*' => Http::response([['status' => 'ENQUEUED']], 200)]);

    checkPayouts();

    expect($log->fresh()->status)->toBe('unconfirmed')
        ->and($log->loan->fresh()->status)->toBe(LoanStatus::Active)
        ->and(InAppNotification::where('type', 'disbursement_unconfirmed')->exists())->toBeTrue();
});

it('hands Zamtel payouts (no status API) to staff only once they are stale', function () {
    $fresh = acceptedPayout('zamtel_kwacha');
    $stale = acceptedPayout('zamtel_kwacha', ageMinutes: 49 * 60);
    Http::fake();

    checkPayouts();

    expect($fresh->fresh()->status)->toBe('processing')
        ->and($stale->fresh()->status)->toBe('unconfirmed');
    Http::assertNothingSent();
});

it('treats a provider error as "try again later", not as a failure', function () {
    $log = acceptedPayout('pawapay');
    Http::fake(['*/payouts/*' => Http::response('Bad gateway', 502)]);

    checkPayouts();

    expect($log->fresh()->status)->toBe('processing')
        ->and($log->loan->fresh()->status)->toBe(LoanStatus::Active);
});

it('gives the provider a few minutes before the first check', function () {
    $log = acceptedPayout('pawapay', ageMinutes: 1);
    Http::fake();

    checkPayouts();

    Http::assertNothingSent();
    expect($log->fresh()->status)->toBe('processing');
});

it('is scheduled every 30 minutes', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'lendr:check-payouts'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/30 * * * *');
});
