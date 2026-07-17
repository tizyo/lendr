<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanAgreement;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;
use App\Services\ESignatureService;
use Illuminate\Support\Facades\Hash;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function esigAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function esigLoan(): Loan
{
    $type     = LoanType::factory()->create();
    $plan     = LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create(['phone' => '0970000001']);

    return Loan::factory()->create([
        'borrower_id'  => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'status'       => LoanStatus::Approved,
        'loan_number'  => 'LN-ESIG-001',
    ]);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

test('returns null when no agreement exists yet', function () {
    $admin = esigAdmin();
    $loan  = esigLoan();

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.loans.agreement.show', $loan))
        ->assertOk();

    expect($resp->json('data'))->toBeNull();
});

test('can generate a loan agreement PDF', function () {
    $admin = esigAdmin();
    $loan  = esigLoan();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loans.agreement.generate', $loan))
        ->assertCreated();

    $data = $resp->json('data');
    expect($data['status'])->toBe('pending')
        ->and($data['has_pdf'])->toBeTrue()
        ->and($data['document_hash'])->not->toBeNull();
});

test('re-generating resets a previously signed agreement', function () {
    $admin     = esigAdmin();
    $loan      = esigLoan();
    $service   = app(ESignatureService::class);

    $agreement = $service->generate($loan, $admin->id);
    $agreement->update(['status' => 'signed', 'signed_at' => now()]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.agreement.generate', $loan))
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');
});

test('can send signing OTP', function () {
    $admin   = esigAdmin();
    $loan    = esigLoan();
    $service = app(ESignatureService::class);
    $service->generate($loan, $admin->id);

    // enable debug so OTP is returned
    config(['app.debug' => true]);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loans.agreement.send-otp', $loan))
        ->assertOk();

    expect($resp->json('data.otp_sent'))->toBeTrue()
        ->and($resp->json('data.otp'))->toHaveLength(6);
});

test('returns 422 if sending OTP before PDF is generated', function () {
    $admin = esigAdmin();
    $loan  = esigLoan();

    LoanAgreement::create([
        'loan_id' => $loan->id,
        'status'  => 'pending',
        'pdf_path' => null,
    ]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.agreement.send-otp', $loan))
        ->assertUnprocessable();
});

test('can sign agreement with valid OTP', function () {
    $admin   = esigAdmin();
    $loan    = esigLoan();
    $service = app(ESignatureService::class);

    $agreement = $service->generate($loan, $admin->id);
    $otp       = $service->sendOtp($agreement);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loans.agreement.sign', $loan), ['otp' => $otp])
        ->assertOk();

    expect($resp->json('data.status'))->toBe('signed')
        ->and($resp->json('data.signed_at'))->not->toBeNull();
});

test('rejects invalid OTP', function () {
    $admin   = esigAdmin();
    $loan    = esigLoan();
    $service = app(ESignatureService::class);

    $agreement = $service->generate($loan, $admin->id);
    $service->sendOtp($agreement);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.agreement.sign', $loan), ['otp' => '000000'])
        ->assertUnprocessable();
});

test('rejects signing an already signed agreement', function () {
    $admin   = esigAdmin();
    $loan    = esigLoan();
    $service = app(ESignatureService::class);

    $agreement = $service->generate($loan, $admin->id);
    $otp       = $service->sendOtp($agreement);
    $service->sign($agreement, $otp);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.agreement.sign', $loan), ['otp' => $otp])
        ->assertUnprocessable();
});

test('audit trail records all events', function () {
    $admin   = esigAdmin();
    $loan    = esigLoan();
    $service = app(ESignatureService::class);

    $agreement = $service->generate($loan, $admin->id);
    $otp       = $service->sendOtp($agreement);
    $service->sign($agreement, $otp);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.loans.agreement.audit', $loan))
        ->assertOk();

    $events = collect($resp->json('data.events'))->pluck('event');
    expect($events)->toContain('generated')
        ->and($events)->toContain('otp_sent')
        ->and($events)->toContain('signed');
});

test('download returns PDF bytes for a generated agreement', function () {
    $admin   = esigAdmin();
    $loan    = esigLoan();
    $service = app(ESignatureService::class);
    $service->generate($loan, $admin->id);

    $resp = $this->actingAs($admin)
        ->get(route('api.v1.loans.agreement.download', $loan));

    expect($resp->status())->toBe(200)
        ->and($resp->headers->get('Content-Type'))->toContain('application/pdf');
});

test('download returns 404 when no PDF generated', function () {
    $admin = esigAdmin();
    $loan  = esigLoan();

    LoanAgreement::create(['loan_id' => $loan->id, 'status' => 'pending']);

    $this->actingAs($admin)
        ->getJson(route('api.v1.loans.agreement.download', $loan))
        ->assertNotFound();
});

test('voided agreement cannot be signed', function () {
    $admin   = esigAdmin();
    $loan    = esigLoan();
    $service = app(ESignatureService::class);

    $agreement = $service->generate($loan, $admin->id);
    $otp       = $service->sendOtp($agreement);
    $service->void($agreement, 'test void');

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.agreement.sign', $loan), ['otp' => $otp])
        ->assertUnprocessable();
});
