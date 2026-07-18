<?php

use App\Enums\KycStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\KycDocument;
use App\Models\Tenant\User;

function kycWorkflowUser(): User
{
    // BranchManager has kyc.review (LoanOfficer only has kyc.view/kyc.upload)
    return User::factory()->create(['role' => UserRole::BranchManager, 'is_active' => true]);
}

function kycDoc(Borrower $borrower, KycStatus $status = KycStatus::Pending): KycDocument
{
    return KycDocument::factory()->create([
        'borrower_id' => $borrower->id,
        'document_type' => 'national_id_front',
        'file_path' => 'kyc/test/doc.jpg',
        'status' => $status,
    ]);
}

// ─── State Machine ────────────────────────────────────────────────────────────

test('pending document can be moved to under_review via start-review', function () {
    $user = kycWorkflowUser();
    $borrower = Borrower::factory()->create();
    $doc = kycDoc($borrower, KycStatus::Pending);

    $response = $this->actingAs($user)
        ->postJson(route('api.v1.kyc.start-review', $doc));

    $response->assertOk();
    expect($response->json('data.status'))->toBe('under_review');
    expect($doc->fresh()->status)->toBe(KycStatus::UnderReview);
});

test('start-review returns 422 if document is not pending', function () {
    $user = kycWorkflowUser();
    $borrower = Borrower::factory()->create();
    $doc = kycDoc($borrower, KycStatus::Verified);

    $response = $this->actingAs($user)
        ->postJson(route('api.v1.kyc.start-review', $doc));

    $response->assertStatus(422);
});

test('under_review document can be approved', function () {
    $user = kycWorkflowUser();
    $borrower = Borrower::factory()->create(['kyc_verified' => false]);
    $doc = kycDoc($borrower, KycStatus::UnderReview);

    $response = $this->actingAs($user)
        ->putJson(route('api.v1.kyc.review', $doc), ['action' => 'approve']);

    $response->assertOk();
    expect($response->json('data.status'))->toBe('verified');
    expect($doc->fresh()->status)->toBe(KycStatus::Verified);
    expect($borrower->fresh()->kyc_verified)->toBeTrue();
});

test('under_review document can be rejected with reason', function () {
    $user = kycWorkflowUser();
    $borrower = Borrower::factory()->create();
    $doc = kycDoc($borrower, KycStatus::UnderReview);

    $response = $this->actingAs($user)
        ->putJson(route('api.v1.kyc.review', $doc), [
            'action' => 'reject',
            'rejection_reason' => 'Document is blurry',
        ]);

    $response->assertOk();
    expect($response->json('data.status'))->toBe('rejected');
    expect($doc->fresh()->rejection_reason)->toBe('Document is blurry');
});

test('pending document cannot be directly approved (must go through under_review)', function () {
    $user = kycWorkflowUser();
    $borrower = Borrower::factory()->create();
    $doc = kycDoc($borrower, KycStatus::Pending);

    $response = $this->actingAs($user)
        ->putJson(route('api.v1.kyc.review', $doc), ['action' => 'approve']);

    $response->assertStatus(422);
    expect($doc->fresh()->status)->toBe(KycStatus::Pending);
});

test('verified document cannot be deleted', function () {
    $user = kycWorkflowUser();
    $borrower = Borrower::factory()->create();
    $doc = kycDoc($borrower, KycStatus::Verified);

    $response = $this->actingAs($user)
        ->deleteJson(route('api.v1.kyc.destroy', $doc));

    $response->assertStatus(422);
});

// ─── Borrower Documents Summary ───────────────────────────────────────────────

test('can list all kyc documents for a borrower with summary', function () {
    $user = kycWorkflowUser();
    $borrower = Borrower::factory()->create(['kyc_verified' => true]);
    kycDoc($borrower, KycStatus::Verified);
    kycDoc($borrower, KycStatus::Pending);

    $response = $this->actingAs($user)
        ->getJson(route('api.v1.kyc.borrower-documents', $borrower));

    $response->assertOk();
    expect($response->json('data.summary.total'))->toBe(2);
    expect($response->json('data.summary.verified'))->toBe(1);
    expect($response->json('data.summary.pending'))->toBe(1);
    expect($response->json('data.summary.kyc_verified'))->toBeTrue();
});

// ─── Expiry Tracking ──────────────────────────────────────────────────────────

test('expire-kyc-documents command marks expired verified docs', function () {
    $borrower = Borrower::factory()->create(['kyc_verified' => true]);
    $expired = KycDocument::factory()->create([
        'borrower_id' => $borrower->id,
        'document_type' => 'passport',
        'file_path' => 'kyc/test/expired.jpg',
        'status' => KycStatus::Verified,
        'expires_at' => now()->subDay(),
    ]);
    $current = KycDocument::factory()->create([
        'borrower_id' => $borrower->id,
        'document_type' => 'selfie',
        'file_path' => 'kyc/test/current.jpg',
        'status' => KycStatus::Verified,
        'expires_at' => now()->addYear(),
    ]);

    $this->artisan('lendr:expire-kyc-documents')
        ->assertSuccessful();

    expect($expired->fresh()->status)->toBe(KycStatus::Expired);
    expect($current->fresh()->status)->toBe(KycStatus::Verified);
});

test('expire command resets borrower kyc_verified when all docs are expired', function () {
    $borrower = Borrower::factory()->create(['kyc_verified' => true]);
    KycDocument::factory()->create([
        'borrower_id' => $borrower->id,
        'document_type' => 'passport',
        'file_path' => 'kyc/test/only.jpg',
        'status' => KycStatus::Verified,
        'expires_at' => now()->subDay(),
    ]);

    $this->artisan('lendr:expire-kyc-documents')->assertSuccessful();

    expect($borrower->fresh()->kyc_verified)->toBeFalse();
});

// ─── KYC-Gated Loan Application ───────────────────────────────────────────────

test('borrower with unverified kyc cannot apply for a loan', function () {
    $borrower = Borrower::factory()->create(['kyc_verified' => false, 'is_active' => true, 'portal_access' => true]);
    $token = $borrower->createToken('pwa')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/v1/me/loans/apply', [
            'loan_type_id' => 1,
            'loan_plan_id' => 1,
            'principal_amount' => 1000,
            'tenure' => 6,
        ]);

    $response->assertStatus(403);
    expect($response->json('message'))->toContain('KYC');
});

test('pending queue includes under_review documents', function () {
    $user = kycWorkflowUser();
    $borrower = Borrower::factory()->create();
    kycDoc($borrower, KycStatus::UnderReview);

    $response = $this->actingAs($user)
        ->getJson(route('api.v1.kyc.pending'));

    $response->assertOk();
    $statuses = collect($response->json('data'))->pluck('status')->toArray();
    expect($statuses)->toContain('under_review');
});
