<?php

use App\Enums\FundTransactionType;
use App\Enums\LoanStatus;
use App\Jobs\DisburseMobileMoneyJob;
use App\Models\Tenant\BorrowerNotification;
use App\Models\Tenant\DisbursementLog;
use App\Models\Tenant\FundBalance;
use App\Models\Tenant\FundTransaction;
use App\Models\Tenant\InAppNotification;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Payment;
use App\Models\Tenant\User;
use App\Services\Payment\DisbursementOutcomeService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function momoSettings(array $settings): void
{
    foreach ($settings as $key => $value) {
        DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now()]);
    }
}

function approvedMomoLoan(array $attrs = []): Loan
{
    return Loan::factory()->approved()->create(array_merge([
        'loan_plan_id' => makePlan()->id,
        'principal_amount' => 5000,
        'interest_amount' => 1500,
        'tenure' => 6,
    ], $attrs));
}

function disburseVia(User $admin, Loan $loan, string $method = 'airtel_money')
{
    return test()->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.loans.disburse', $loan), [
            'disbursement_method' => $method,
            'disbursement_account' => '0971234567',
            'disbursement_date' => now()->toDateString(),
        ]);
}

/** Airtel token always succeeds; the disbursement call answers with $response. */
function fakeAirtel($response): void
{
    Http::fake([
        '*/auth/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
        '*/disbursements/*' => $response,
    ]);
}

function airtelOk()
{
    return Http::response([
        'data' => ['transaction' => ['id' => 'AT-123', 'status' => 'TS']],
        'status' => ['success' => true, 'response_code' => 'DP00900001001', 'message' => 'Success'],
    ], 200);
}

function borrowerToldDisbursed(Loan $loan): bool
{
    return BorrowerNotification::where('borrower_id', $loan->borrower_id)->where('type', 'disbursed')->exists();
}

function staffAlerted(string $type): bool
{
    return InAppNotification::where('type', $type)->exists();
}

beforeEach(function () {
    momoSettings(['airtel_client_id' => 'id', 'airtel_client_secret' => 'secret', 'pawapay_api_key' => 'pp-key']);
});

// ─── Rejected → reversed ─────────────────────────────────────────────────────

it('reverses the disbursement when Airtel answers 200 but refuses the transfer', function () {
    $admin = actingAsAdmin();
    $loan = approvedMomoLoan();
    $balanceBefore = (float) FundBalance::current()->available_balance;

    fakeAirtel(Http::response(['status' => ['success' => false, 'response_code' => 'DP00900001012', 'message' => 'Invalid PIN']], 200));

    disburseVia($admin, $loan)->assertOk();

    $loan->refresh();
    $log = DisbursementLog::where('loan_id', $loan->id)->sole();
    $balance = FundBalance::current();

    expect($loan->status)->toBe(LoanStatus::Approved)
        ->and($loan->disbursement_date)->toBeNull()
        ->and($loan->schedule()->count())->toBe(0)
        ->and($log->status)->toBe('reversed')
        ->and($log->failure_reason)->toContain('Invalid PIN')
        ->and((float) $balance->available_balance)->toBe($balanceBefore)
        ->and((float) $balance->total_disbursed)->toBe(0.0)
        ->and(FundTransaction::where('type', FundTransactionType::DisburseReversal->value)->count())->toBe(1)
        ->and($loan->statusLogs()->latest('id')->value('to_status'))->toBe('approved')
        ->and(borrowerToldDisbursed($loan))->toBeFalse()
        ->and(staffAlerted('disbursement_failed'))->toBeTrue();
});

it('no longer treats missing provider credentials as a silent success', function () {
    $admin = actingAsAdmin();
    $loan = approvedMomoLoan();
    DB::table('settings')->whereIn('key', ['airtel_client_id', 'airtel_client_secret'])->delete();
    Http::fake();

    disburseVia($admin, $loan)->assertOk();

    expect($loan->fresh()->status)->toBe(LoanStatus::Approved)
        ->and(DisbursementLog::where('loan_id', $loan->id)->value('failure_reason'))->toContain('not configured');
    Http::assertNothingSent();
});

it('treats an auth failure as definitive — the payout was never sent', function () {
    $admin = actingAsAdmin();
    $loan = approvedMomoLoan();

    Http::fake(['*/auth/oauth2/token' => Http::response('upstream down', 503)]);

    disburseVia($admin, $loan)->assertOk();

    expect($loan->fresh()->status)->toBe(LoanStatus::Approved);
    Http::assertSentCount(1); // only the token request
});

it('reverses when the provider refuses with a 4xx', function () {
    $admin = actingAsAdmin();
    $loan = approvedMomoLoan();

    fakeAirtel(Http::response(['error' => 'insufficient float'], 400));

    disburseVia($admin, $loan)->assertOk();

    expect($loan->fresh()->status)->toBe(LoanStatus::Approved);
});

// ─── Accepted ────────────────────────────────────────────────────────────────

it('tells the borrower only once the provider accepts the payout', function () {
    $admin = actingAsAdmin();
    $loan = approvedMomoLoan();

    fakeAirtel(airtelOk());

    disburseVia($admin, $loan)->assertOk();

    $log = DisbursementLog::where('loan_id', $loan->id)->sole();

    expect($loan->fresh()->status)->toBe(LoanStatus::Active)
        ->and($log->status)->toBe('processing')
        ->and($log->provider_reference)->toBe('AT-123')
        ->and(borrowerToldDisbursed($loan))->toBeTrue();
});

it('still notifies immediately for cash disbursements', function () {
    $admin = actingAsAdmin();
    $loan = approvedMomoLoan();
    Http::fake();

    disburseVia($admin, $loan, 'cash')->assertOk();

    expect(borrowerToldDisbursed($loan))->toBeTrue()
        ->and(DisbursementLog::where('loan_id', $loan->id)->exists())->toBeFalse();
    Http::assertNothingSent();
});

// ─── PawaPay rules (job level — PawaPay isn't a staff-selectable method) ─────

it('sends PawaPay a UUID payoutId and digits-only MSISDN', function () {
    $loan = approvedMomoLoan(['status' => LoanStatus::Active, 'disbursement_method' => 'pawapay', 'disbursement_account' => '0761234567']);
    Http::fake(['*/payouts' => Http::response(['payoutId' => 'x', 'status' => 'ACCEPTED'], 200)]);

    dispatch_sync(new DisburseMobileMoneyJob($loan));

    expect(Str::isUuid(DisbursementLog::where('loan_id', $loan->id)->value('provider_reference')))->toBeTrue();
    Http::assertSent(fn (HttpRequest $r) => Str::isUuid($r['payoutId'])
        && $r['recipient']['address']['value'] === '260761234567'
        && strlen($r['statementDescription']) <= 22);
});

it('treats a PawaPay 200 REJECTED as a refusal, not a success', function () {
    $loan = approvedMomoLoan(['status' => LoanStatus::Active, 'disbursement_method' => 'pawapay', 'disbursement_account' => '0761234567']);
    Http::fake(['*/payouts' => Http::response([
        'status' => 'REJECTED',
        'rejectionReason' => ['rejectionCode' => 'INVALID_RECIPIENT', 'rejectionMessage' => 'Recipient wallet not found'],
    ], 200)]);

    dispatch_sync(new DisburseMobileMoneyJob($loan));

    expect($loan->fresh()->status)->toBe(LoanStatus::Approved)
        ->and(DisbursementLog::where('loan_id', $loan->id)->value('failure_reason'))->toContain('Recipient wallet not found');
});

// ─── Unknown outcome ─────────────────────────────────────────────────────────

it('changes nothing and alerts staff when the payout outcome is unknown', function () {
    $admin = actingAsAdmin();
    $loan = approvedMomoLoan();

    fakeAirtel(Http::response('Service Unavailable', 503));

    disburseVia($admin, $loan)->assertOk();

    expect($loan->fresh()->status)->toBe(LoanStatus::Active)
        ->and((float) FundBalance::current()->total_disbursed)->toBe(5000.0)
        ->and(DisbursementLog::where('loan_id', $loan->id)->value('status'))->toBe('unconfirmed')
        ->and(borrowerToldDisbursed($loan))->toBeFalse()
        ->and(staffAlerted('disbursement_unconfirmed'))->toBeTrue();
});

it('lets staff confirm an unconfirmed payout was not sent, which reverses it', function () {
    $admin = actingAsAdmin();
    $loan = approvedMomoLoan();
    fakeAirtel(Http::response('timeout', 504));
    disburseVia($admin, $loan);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.payout.resolve', $loan), ['outcome' => 'not_sent', 'notes' => 'Not in Airtel portal'])
        ->assertOk();

    expect($loan->fresh()->status)->toBe(LoanStatus::Approved)
        ->and(DisbursementLog::where('loan_id', $loan->id)->value('status'))->toBe('reversed')
        ->and((float) FundBalance::current()->total_disbursed)->toBe(0.0);
});

it('lets staff confirm an unconfirmed payout was sent, which notifies the borrower', function () {
    $admin = actingAsAdmin();
    $loan = approvedMomoLoan();
    fakeAirtel(Http::response('timeout', 504));
    disburseVia($admin, $loan);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.payout.resolve', $loan), ['outcome' => 'sent', 'notes' => 'Completed in Airtel portal'])
        ->assertOk();

    expect($loan->fresh()->status)->toBe(LoanStatus::Active)
        ->and(DisbursementLog::where('loan_id', $loan->id)->value('status'))->toBe('processing')
        ->and(borrowerToldDisbursed($loan))->toBeTrue();
});

it('refuses to resolve a payout that is not unconfirmed', function () {
    $admin = actingAsAdmin();
    $loan = approvedMomoLoan();
    fakeAirtel(airtelOk());
    disburseVia($admin, $loan);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.payout.resolve', $loan), ['outcome' => 'not_sent', 'notes' => 'x'])
        ->assertStatus(422);

    expect($loan->fresh()->status)->toBe(LoanStatus::Active);
});

// ─── Safety rails ────────────────────────────────────────────────────────────

it('never sends a second payout for the same disbursement', function () {
    $admin = actingAsAdmin();
    $loan = approvedMomoLoan();
    fakeAirtel(airtelOk());

    disburseVia($admin, $loan);
    dispatch_sync(new DisburseMobileMoneyJob($loan->fresh())); // duplicate dispatch

    Http::assertSentCount(2); // one token + one payout, from the first dispatch only
    expect(DisbursementLog::where('loan_id', $loan->id)->count())->toBe(1);
});

it('does not auto-reverse a loan that has already taken a payment', function () {
    actingAsAdmin();
    $loan = approvedMomoLoan(['status' => LoanStatus::Active]);
    Payment::factory()->create(['loan_id' => $loan->id]);

    $log = DisbursementLog::create([
        'loan_id' => $loan->id, 'gateway' => 'airtel_money', 'reference' => 'LENDR-DISB-T1',
        'amount' => 5000, 'status' => 'initiated',
    ]);

    app(DisbursementOutcomeService::class)->rejected($loan, $log, 'Rejected late');

    expect($loan->fresh()->status)->toBe(LoanStatus::Active)
        ->and($log->fresh()->status)->toBe('failed')
        ->and(InAppNotification::where('type', 'disbursement_failed')->value('body'))->toContain('NOT reversed');
});
