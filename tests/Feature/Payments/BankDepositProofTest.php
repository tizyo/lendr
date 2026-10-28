<?php

use App\Enums\LoanStatus;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\BorrowerNotification;
use App\Models\Tenant\InAppNotification;
use App\Models\Tenant\KycDocument;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Payment;
use App\Models\Tenant\PaymentProof;
use App\Models\Tenant\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// ─── Helpers ─────────────────────────────────────────────────────────────────

beforeEach(function () {
    Storage::fake('private');
});

function withBankAccount(): void
{
    foreach (['bank_name' => 'Zanaco', 'bank_account_name' => 'Vozara Finance Ltd', 'bank_account_number' => '0123456789', 'bank_branch' => 'Cairo Road'] as $k => $v) {
        DB::table('settings')->updateOrInsert(['key' => $k], ['value' => $v, 'updated_at' => now()]);
    }
}

/** A properly disbursed loan (schedule + fund entries) owned by $borrower. */
function disbursedLoanFor(Borrower $borrower, User $admin): Loan
{
    $loan = Loan::factory()->approved()->create([
        'borrower_id' => $borrower->id,
        'loan_plan_id' => makePlan()->id,
        'principal_amount' => 5000,
        'interest_amount' => 1500,
        'tenure' => 6,
    ]);

    test()->actingAs($admin)->postJson(route('api.v1.loans.disburse', $loan), [
        'disbursement_method' => 'cash',
        'disbursement_date' => now()->toDateString(),
    ])->assertOk();

    return $loan->fresh();
}

function uploadProof(Borrower $borrower, Loan $loan, array $overrides = [])
{
    return test()->actingAs($borrower, 'sanctum')->post(route('api.v1.borrower.loans.payment-proofs.store', $loan->id), array_merge([
        'amount' => 1200,
        'paid_on' => now()->toDateString(),
        'bank_reference' => 'ZAN-778812',
        'file' => UploadedFile::fake()->image('slip.jpg'),
    ], $overrides), ['Accept' => 'application/json']);
}

// ─── Borrower ────────────────────────────────────────────────────────────────

it('shows the borrower the bank account and their loan number as reference', function () {
    withBankAccount();
    $borrower = Borrower::factory()->create();
    $loan = Loan::factory()->create(['borrower_id' => $borrower->id, 'status' => LoanStatus::Active]);

    $this->actingAs($borrower, 'sanctum')
        ->getJson(route('api.v1.borrower.loans.bank-details', $loan->id))
        ->assertOk()
        ->assertJsonPath('data.bank.bank_name', 'Zanaco')
        ->assertJsonPath('data.bank.account_number', '0123456789')
        ->assertJsonPath('data.reference', $loan->loan_number);
});

it('offers no bank deposit when the lender has not set an account up', function () {
    $borrower = Borrower::factory()->create();
    $loan = Loan::factory()->create(['borrower_id' => $borrower->id, 'status' => LoanStatus::Active]);

    $this->actingAs($borrower, 'sanctum')
        ->getJson(route('api.v1.borrower.loans.bank-details', $loan->id))
        ->assertJsonPath('data.bank', null);
});

it('stores the uploaded slip privately and alerts staff', function () {
    $admin = actingAsAdmin();
    $borrower = Borrower::factory()->create();
    $loan = Loan::factory()->create(['borrower_id' => $borrower->id, 'status' => LoanStatus::Active]);

    uploadProof($borrower, $loan)->assertCreated();

    $proof = PaymentProof::sole();
    expect($proof->status)->toBe('pending')
        ->and((float) $proof->amount)->toBe(1200.0)
        ->and(InAppNotification::where('user_id', $admin->id)->where('type', 'payment_proof_submitted')->exists())->toBeTrue();
    Storage::disk('private')->assertExists($proof->file_path);
});

it('will not take a slip for someone else\'s loan', function () {
    $loan = Loan::factory()->create(['status' => LoanStatus::Active]);

    uploadProof(Borrower::factory()->create(), $loan)->assertNotFound();

    expect(PaymentProof::count())->toBe(0);
});

it('will not take a slip for a closed loan', function () {
    $borrower = Borrower::factory()->create();
    $loan = Loan::factory()->create(['borrower_id' => $borrower->id, 'status' => LoanStatus::Completed]);

    uploadProof($borrower, $loan)->assertStatus(422);
});

it('rejects slips dated in the future or that are not images/PDFs', function () {
    $borrower = Borrower::factory()->create();
    $loan = Loan::factory()->create(['borrower_id' => $borrower->id, 'status' => LoanStatus::Active]);

    uploadProof($borrower, $loan, [
        'paid_on' => now()->addDay()->toDateString(),
        'file' => UploadedFile::fake()->create('slip.exe', 10, 'application/x-msdownload'),
    ])->assertStatus(422)->assertJsonValidationErrors(['paid_on', 'file']);
});

// ─── Staff review ────────────────────────────────────────────────────────────

it('approving records the payment as the bank shows it', function () {
    $admin = actingAsAdmin();
    $borrower = Borrower::factory()->create();
    $loan = disbursedLoanFor($borrower, $admin);
    uploadProof($borrower, $loan, ['amount' => 1200])->assertCreated();
    $proof = PaymentProof::sole();

    $this->actingAs($admin)
        ->postJson(route('api.v1.payment-proofs.approve', $proof), ['amount' => 1150, 'paid_on' => now()->subDay()->toDateString()])
        ->assertOk();

    $payment = Payment::where('loan_id', $loan->id)->sole();
    $proof->refresh();

    expect((float) $payment->amount)->toBe(1150.0)
        ->and($payment->payment_method->value ?? $payment->payment_method)->toBe('bank_transfer')
        ->and($payment->reference)->toBe('ZAN-778812')
        ->and($proof->status)->toBe('approved')
        ->and($proof->payment_id)->toBe($payment->id)
        ->and($proof->reviewed_by)->toBe($admin->id)
        ->and((float) $loan->fresh()->total_paid)->toBe(1150.0);
});

it('cannot approve the same slip twice', function () {
    $admin = actingAsAdmin();
    $borrower = Borrower::factory()->create();
    $loan = disbursedLoanFor($borrower, $admin);
    uploadProof($borrower, $loan);
    $proof = PaymentProof::sole();

    $this->actingAs($admin)->postJson(route('api.v1.payment-proofs.approve', $proof))->assertOk();
    $this->actingAs($admin)->postJson(route('api.v1.payment-proofs.approve', $proof))->assertStatus(422);

    expect(Payment::where('loan_id', $loan->id)->count())->toBe(1);
});

it('rejecting tells the borrower why and records nothing', function () {
    $admin = actingAsAdmin();
    $borrower = Borrower::factory()->create();
    $loan = Loan::factory()->create(['borrower_id' => $borrower->id, 'status' => LoanStatus::Active]);
    uploadProof($borrower, $loan);
    $proof = PaymentProof::sole();

    $this->actingAs($admin)
        ->postJson(route('api.v1.payment-proofs.reject', $proof), ['reason' => 'Not on our statement'])
        ->assertOk();

    $note = BorrowerNotification::where('borrower_id', $borrower->id)->sole();

    expect($proof->fresh()->status)->toBe('rejected')
        ->and($note->type)->toBe('payment_proof_rejected')
        ->and($note->body)->toContain('Not on our statement')
        ->and(Payment::count())->toBe(0);

    $this->actingAs($borrower, 'sanctum')
        ->getJson(route('api.v1.borrower.payment-proofs.index'))
        ->assertJsonPath('data.0.status', 'rejected')
        ->assertJsonPath('data.0.rejection_reason', 'Not on our statement');
});

it('lists pending slips for staff and streams the file', function () {
    $admin = actingAsAdmin();
    $borrower = Borrower::factory()->create();
    $loan = Loan::factory()->create(['borrower_id' => $borrower->id, 'status' => LoanStatus::Active]);
    uploadProof($borrower, $loan);
    $proof = PaymentProof::sole();

    $this->actingAs($admin)
        ->getJson(route('api.v1.payment-proofs.index'))
        ->assertOk()
        ->assertJsonPath('data.pending_count', 1)
        ->assertJsonPath('data.data.0.loan.loan_number', $loan->loan_number);

    $this->actingAs($admin)->get(route('api.v1.payment-proofs.file', $proof))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('keeps slips away from staff without payment permissions', function () {
    actingAsAdmin(); // seeds roles
    $officer = User::factory()->create(['role' => \App\Enums\UserRole::Auditor, 'is_active' => true]);
    $borrower = Borrower::factory()->create();
    $loan = Loan::factory()->create(['borrower_id' => $borrower->id, 'status' => LoanStatus::Active]);
    uploadProof($borrower, $loan);

    $this->actingAs($officer)->postJson(route('api.v1.payment-proofs.approve', PaymentProof::sole()))->assertForbidden();
});

// ─── KYC files (private disk) ────────────────────────────────────────────────

it('streams KYC documents to staff through the API instead of a public URL', function () {
    $admin = actingAsAdmin();
    $doc = KycDocument::factory()->create(['file_path' => 'kyc/1/nrc.jpg', 'file_name' => 'nrc.jpg']);
    Storage::disk('private')->put('kyc/1/nrc.jpg', 'image-bytes');

    $url = $this->actingAs($admin)->getJson(route('api.v1.kyc.view', $doc))->assertOk()->json('data.url');

    expect($url)->toBe(route('api.v1.kyc.file', $doc));

    $this->actingAs($admin)->get($url)->assertOk()->assertStreamedContent('image-bytes');
});
