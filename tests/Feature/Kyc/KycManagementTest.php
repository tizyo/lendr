<?php

use App\Enums\KycStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\KycDocument;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Storage;

function kycUser(): User
{
    return User::factory()->create(['role' => UserRole::LoanOfficer, 'is_active' => true]);
}

// ─── Pending Queue ────────────────────────────────────────────────────────────

test('pending kyc queue returns pending and under_review documents', function () {
    $user     = kycUser();
    $borrower = Borrower::factory()->create();

    KycDocument::factory()->pending()->count(2)->create(['borrower_id' => $borrower->id]);
    KycDocument::factory()->verified()->create(['borrower_id' => $borrower->id]);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.kyc.pending'));

    $response->assertOk();
    $statuses = collect($response->json('data'))->pluck('status')->unique()->values()->toArray();
    // Queue now includes pending and under_review (not verified/rejected/expired)
    expect($statuses)->toContain('pending');
    expect($statuses)->not->toContain('verified');
});

test('pending kyc documents include borrower info', function () {
    $user     = kycUser();
    $borrower = Borrower::factory()->create(['first_name' => 'Chanda', 'last_name' => 'Mutale']);
    KycDocument::factory()->pending()->create(['borrower_id' => $borrower->id]);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.kyc.pending'));

    $doc = $response->json('data.0');
    expect($doc['borrower']['full_name'])->toContain('Chanda');
});

// ─── Review: Approve ─────────────────────────────────────────────────────────

test('an under_review document can be approved', function () {
    $user     = kycUser();
    $borrower = Borrower::factory()->create();
    // Must be under_review before it can be approved (state machine)
    $doc      = KycDocument::factory()->create([
        'borrower_id' => $borrower->id,
        'status'      => KycStatus::UnderReview,
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->putJson(route('api.v1.kyc.review', $doc), ['action' => 'approve']);

    $response->assertOk()
             ->assertJsonPath('data.status', 'verified');

    expect($doc->fresh()->status)->toBe(KycStatus::Verified);
    expect($doc->fresh()->reviewed_by)->toBe($user->id);
});

test('an under_review document can be rejected with a reason', function () {
    $user     = kycUser();
    $borrower = Borrower::factory()->create();
    $doc      = KycDocument::factory()->create([
        'borrower_id' => $borrower->id,
        'status'      => KycStatus::UnderReview,
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->putJson(route('api.v1.kyc.review', $doc), [
            'action'           => 'reject',
            'rejection_reason' => 'Image is blurry.',
        ]);

    $response->assertOk()
             ->assertJsonPath('data.status', 'rejected');

    expect($doc->fresh()->rejection_reason)->toBe('Image is blurry.');
});

test('rejection requires a reason', function () {
    $user     = kycUser();
    $borrower = Borrower::factory()->create();
    $doc      = KycDocument::factory()->pending()->create(['borrower_id' => $borrower->id]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->putJson(route('api.v1.kyc.review', $doc), ['action' => 'reject'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['rejection_reason']);
});

test('an already verified document cannot be reviewed again', function () {
    $user     = kycUser();
    $borrower = Borrower::factory()->create();
    $doc      = KycDocument::factory()->verified()->create(['borrower_id' => $borrower->id]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->putJson(route('api.v1.kyc.review', $doc), ['action' => 'approve'])
        ->assertStatus(422);
});

test('review action must be approve or reject', function () {
    $user     = kycUser();
    $borrower = Borrower::factory()->create();
    $doc      = KycDocument::factory()->pending()->create(['borrower_id' => $borrower->id]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->putJson(route('api.v1.kyc.review', $doc), ['action' => 'delete'])
        ->assertStatus(422);
});

// ─── Delete ───────────────────────────────────────────────────────────────────

test('a kyc document can be deleted', function () {
    Storage::fake('private');

    $user     = kycUser();
    $borrower = Borrower::factory()->create();
    $doc      = KycDocument::factory()->create([
        'borrower_id' => $borrower->id,
        'file_path'   => 'kyc/test/sample.jpg',
    ]);

    Storage::disk('private')->put('kyc/test/sample.jpg', 'fake content');

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->deleteJson(route('api.v1.kyc.destroy', $doc))
        ->assertOk();

    expect(KycDocument::find($doc->id))->toBeNull();
});

// ─── KYC status sync on borrower ─────────────────────────────────────────────

test('borrower kyc_verified is set after document approval', function () {
    $user     = kycUser();
    $borrower = Borrower::factory()->create(['kyc_verified' => false]);
    $doc      = KycDocument::factory()->create([
        'borrower_id' => $borrower->id,
        'status'      => KycStatus::UnderReview,
    ]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->putJson(route('api.v1.kyc.review', $doc), ['action' => 'approve'])
        ->assertOk();

    expect($borrower->fresh()->kyc_verified)->toBeTrue();
});
