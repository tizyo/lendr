<?php

use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Campaign;
use App\Models\Tenant\CampaignRecipient;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function campaignAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function makeBorrower(array $attrs = []): Borrower
{
    return Borrower::factory()->create(array_merge(['is_active' => true], $attrs));
}

function makeCampaign(array $attrs = []): Campaign
{
    return Campaign::create(array_merge([
        'name' => 'Test Campaign',
        'type' => 'sms',
        'content' => 'Hello {{name}}, your loan is due.',
        'target_segment' => 'all_borrowers',
        'status' => 'draft',
    ], $attrs));
}

// ─── Tests ────────────────────────────────────────────────────────────────────

test('can create an SMS campaign', function () {
    $admin = campaignAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.campaigns.store'), [
            'name' => 'Payment Reminder',
            'type' => 'sms',
            'content' => 'Your loan repayment is due tomorrow.',
            'target_segment' => 'active_borrowers',
        ])
        ->assertStatus(201);

    expect($resp->json('data.campaign.type'))->toBe('sms')
        ->and($resp->json('data.campaign.status'))->toBe('draft');
});

test('can create an email campaign with subject', function () {
    $admin = campaignAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.campaigns.store'), [
            'name' => 'Monthly Newsletter',
            'type' => 'email',
            'subject' => 'Your Monthly Statement',
            'content' => '<h1>Hello</h1><p>Your statement is ready.</p>',
            'target_segment' => 'all_borrowers',
        ])
        ->assertStatus(201);

    expect($resp->json('data.campaign.type'))->toBe('email')
        ->and($resp->json('data.campaign.subject'))->toBe('Your Monthly Statement');
});

test('can list campaigns', function () {
    $admin = campaignAdmin();
    makeCampaign(['name' => 'Campaign A']);
    makeCampaign(['name' => 'Campaign B']);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.campaigns.index'))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(2);
});

test('creating campaign with scheduled_at sets status to scheduled', function () {
    $admin = campaignAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.campaigns.store'), [
            'name' => 'Scheduled Blast',
            'type' => 'sms',
            'content' => 'Hello!',
            'target_segment' => 'all_borrowers',
            'scheduled_at' => now()->addDay()->toDateTimeString(),
        ])
        ->assertStatus(201);

    expect($resp->json('data.campaign.status'))->toBe('scheduled');
});

test('dispatching campaign to all_borrowers segment sends to all active borrowers', function () {
    $admin = campaignAdmin();
    makeBorrower(['phone' => '+260971000001']);
    makeBorrower(['phone' => '+260971000002']);

    $campaign = makeCampaign(['target_segment' => 'all_borrowers']);

    // Mock SMS service to avoid real sends
    $this->mock(\App\Services\SMS\SmsService::class)->shouldReceive('send')->twice()->andReturn(true);
    $this->mock(\App\Services\Mail\TenantMailService::class);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.campaigns.dispatch', $campaign))
        ->assertOk();

    expect($resp->json('data.total'))->toBe(2)
        ->and($resp->json('data.sent'))->toBe(2);
});

test('dispatching campaign to active_borrowers only includes borrowers with active loans', function () {
    $admin = campaignAdmin();
    $type = LoanType::factory()->create();
    $plan = LoanPlan::factory()->create(['loan_type_id' => $type->id]);

    $activeBorrower = makeBorrower(['phone' => '+260971111111']);
    $inactiveBorrower = makeBorrower(['phone' => '+260972222222']); // no active loan

    Loan::factory()->create([
        'borrower_id' => $activeBorrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'status' => 'active',
    ]);

    $campaign = makeCampaign(['target_segment' => 'active_borrowers']);

    $this->mock(\App\Services\SMS\SmsService::class)->shouldReceive('send')->once()->andReturn(true);
    $this->mock(\App\Services\Mail\TenantMailService::class);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.campaigns.dispatch', $campaign))
        ->assertOk();

    expect($resp->json('data.total'))->toBe(1);
});

test('dry run dispatch does not persist recipient records', function () {
    $admin = campaignAdmin();
    makeBorrower(['phone' => '+260971000099']);
    $campaign = makeCampaign();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.campaigns.dispatch', $campaign), ['dry_run' => true])
        ->assertOk();

    expect($resp->json('data.dry_run'))->toBeTrue();
    expect(CampaignRecipient::count())->toBe(0);
});

test('already completed campaign cannot be dispatched again', function () {
    $admin = campaignAdmin();
    $campaign = makeCampaign(['status' => 'completed']);

    $this->actingAs($admin)
        ->postJson(route('api.v1.campaigns.dispatch', $campaign))
        ->assertStatus(422);
});

test('campaign stats shows delivery and open rates', function () {
    $admin = campaignAdmin();
    $campaign = makeCampaign([
        'status' => 'completed',
        'total_recipients' => 100,
        'sent_count' => 90,
        'failed_count' => 10,
        'opened_count' => 45,
    ]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.campaigns.stats', $campaign))
        ->assertOk();

    expect((float) $resp->json('data.delivery_rate'))->toBe(90.0)
        ->and((float) $resp->json('data.open_rate'))->toBe(50.0);
});

test('tracking open updates recipient status and increments campaign opened count', function () {
    $admin = campaignAdmin();
    $campaign = makeCampaign(['status' => 'completed', 'sent_count' => 1]);
    $recipient = CampaignRecipient::create([
        'campaign_id' => $campaign->id,
        'recipient_address' => '+260971000001',
        'status' => 'sent',
        'sent_at' => now(),
    ]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.campaigns.open', [$campaign, $recipient]))
        ->assertOk();

    expect(CampaignRecipient::find($recipient->id)->status)->toBe('opened');
    expect(Campaign::find($campaign->id)->opened_count)->toBe(1);
});

test('scheduled campaigns are processed by the command', function () {
    $admin = campaignAdmin();
    $campaign = makeCampaign([
        'status' => 'scheduled',
        'scheduled_at' => now()->subMinute()->toDateTimeString(),
    ]);

    $this->artisan('lendr:process-campaigns')->assertExitCode(0);

    expect(Campaign::find($campaign->id)->status)->toBeIn(['running', 'completed']);
});

test('campaign requires name, type, content, and target_segment', function () {
    $admin = campaignAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.campaigns.store'), [])
        ->assertUnprocessable();
});

test('unauthenticated cannot access campaign endpoints', function () {
    $campaign = makeCampaign();

    $this->getJson(route('api.v1.campaigns.index'))->assertUnauthorized();
    $this->postJson(route('api.v1.campaigns.store'))->assertUnauthorized();
    $this->postJson(route('api.v1.campaigns.dispatch', $campaign))->assertUnauthorized();
});
