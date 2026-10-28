<?php

use App\Enums\KycStatus;
use App\Jobs\SendKycStatusNotificationJob;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\BorrowerNotification;
use App\Models\Tenant\KycDocument;
use App\Services\SMS\SmsService;
use Illuminate\Support\Facades\Queue;

function decisionKycDoc(KycStatus $status = KycStatus::UnderReview, array $attrs = []): KycDocument
{
    return KycDocument::factory()->create(array_merge([
        'borrower_id' => Borrower::factory()->create(['first_name' => 'Chanda', 'phone' => '0977000111'])->id,
        'document_type' => 'national_id_front',
        'status' => $status,
    ], $attrs));
}

function fakeSms(): object
{
    $fake = new class
    {
        public array $sent = [];

        public function send(string $phone, string $message): bool
        {
            $this->sent[] = compact('phone', 'message');

            return true;
        }
    };

    app()->instance(SmsService::class, \Mockery::mock(SmsService::class, [
        'send' => true,
    ])->shouldReceive('send')->andReturnUsing(fn ($p, $m) => $fake->send($p, $m))->getMock());

    return $fake;
}

it('tells the borrower when staff approve a document', function () {
    $sms = fakeSms();
    $doc = decisionKycDoc();
    $admin = actingAsAdmin();

    $this->actingAs($admin)
        ->putJson(route('api.v1.kyc.review', $doc), ['action' => 'approve'])
        ->assertOk();

    $note = BorrowerNotification::where('borrower_id', $doc->borrower_id)->sole();

    expect($note->type)->toBe('kyc_verified')
        ->and($note->body)->toContain('NRC (front)')
        ->and($note->data['url'])->toBe('/app/kyc/status')
        ->and($sms->sent)->toHaveCount(1)
        ->and($sms->sent[0]['message'])->toStartWith('Hi Chanda, your NRC (front) has been verified');
});

it('includes the rejection reason so the borrower knows what to fix', function () {
    $sms = fakeSms();
    $doc = decisionKycDoc();

    $this->actingAs(actingAsAdmin())
        ->putJson(route('api.v1.kyc.review', $doc), ['action' => 'reject', 'rejection_reason' => 'Photo is blurry'])
        ->assertOk();

    $note = BorrowerNotification::where('borrower_id', $doc->borrower_id)->sole();

    expect($note->type)->toBe('kyc_rejected')
        ->and($note->body)->toContain('Photo is blurry')
        ->and($sms->sent[0]['message'])->toContain('Photo is blurry');
});

it('tells the borrower when a document expires', function () {
    fakeSms();
    $doc = decisionKycDoc(KycStatus::Verified, ['expires_at' => now()->subDay()]);

    $this->artisan('lendr:expire-kyc-documents')->assertSuccessful();

    expect(BorrowerNotification::where('borrower_id', $doc->borrower_id)->value('type'))->toBe('kyc_expired');
});

it('does not notify for intermediate steps like starting a review', function () {
    Queue::fake();
    $doc = decisionKycDoc(KycStatus::Pending);

    $doc->transitionTo(KycStatus::UnderReview);

    Queue::assertNotPushed(SendKycStatusNotificationJob::class);
});
