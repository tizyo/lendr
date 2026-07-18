<?php

use App\Enums\UserRole;
use App\Models\Tenant\NotificationTemplate;
use App\Models\Tenant\User;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function templateAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

// ─── Index ────────────────────────────────────────────────────────────────────

test('index returns all events with sms and email slots', function () {
    $admin = templateAdmin();

    $response = $this->actingAs($admin)
        ->getJson(route('api.v1.notification-templates.index'))
        ->assertOk();

    $templates = $response->json('data.templates');
    expect($templates)->toBeArray()->not->toBeEmpty();

    $row = collect($templates)->firstWhere('event', 'loan_approved');
    expect($row)->toHaveKeys(['event', 'label', 'sms', 'email']);
})->group('notification-templates');

test('index returns placeholders list', function () {
    $admin = templateAdmin();

    $response = $this->actingAs($admin)
        ->getJson(route('api.v1.notification-templates.index'))
        ->assertOk();

    $placeholders = $response->json('data.placeholders');
    expect($placeholders)->toHaveKey('{{borrower_name}}');
    expect($placeholders)->toHaveKey('{{loan_number}}');
})->group('notification-templates');

test('index shows null for events with no stored template', function () {
    $admin = templateAdmin();

    $response = $this->actingAs($admin)
        ->getJson(route('api.v1.notification-templates.index'))
        ->assertOk();

    $templates = $response->json('data.templates');
    $row = collect($templates)->firstWhere('event', 'otp');
    expect($row['sms'])->toBeNull();
    expect($row['email'])->toBeNull();
})->group('notification-templates');

test('index shows stored template when present', function () {
    $admin = templateAdmin();

    NotificationTemplate::create([
        'event' => 'loan_approved',
        'channel' => 'sms',
        'body' => 'Your loan {{loan_number}} has been approved.',
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('api.v1.notification-templates.index'))
        ->assertOk();

    $templates = $response->json('data.templates');
    $row = collect($templates)->firstWhere('event', 'loan_approved');
    expect($row['sms'])->not->toBeNull();
    expect($row['sms']['body'])->toContain('{{loan_number}}');
})->group('notification-templates');

// ─── Upsert ───────────────────────────────────────────────────────────────────

test('can create a new sms template', function () {
    $admin = templateAdmin();

    $this->actingAs($admin)
        ->putJson(route('api.v1.notification-templates.upsert', ['event' => 'loan_approved', 'channel' => 'sms']), [
            'body' => 'Dear {{borrower_name}}, loan {{loan_number}} approved.',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Template saved.');

    $this->assertDatabaseHas('notification_templates', [
        'event' => 'loan_approved',
        'channel' => 'sms',
    ]);
})->group('notification-templates');

test('can update an existing template', function () {
    $admin = templateAdmin();

    NotificationTemplate::create([
        'event' => 'payment_received',
        'channel' => 'sms',
        'body' => 'Old body',
    ]);

    $this->actingAs($admin)
        ->putJson(route('api.v1.notification-templates.upsert', ['event' => 'payment_received', 'channel' => 'sms']), [
            'body' => 'New body {{amount}}',
        ])
        ->assertOk();

    $this->assertDatabaseHas('notification_templates', [
        'event' => 'payment_received',
        'channel' => 'sms',
        'body' => 'New body {{amount}}',
    ]);

    $this->assertSame(1, NotificationTemplate::where('event', 'payment_received')->where('channel', 'sms')->count());
})->group('notification-templates');

test('can create an email template with subject', function () {
    $admin = templateAdmin();

    $this->actingAs($admin)
        ->putJson(route('api.v1.notification-templates.upsert', ['event' => 'loan_disbursed', 'channel' => 'email']), [
            'subject' => 'Your loan has been disbursed',
            'body' => 'Dear {{borrower_name}}, your loan of {{amount}} has been disbursed.',
            'name' => 'Disbursement Email',
        ])
        ->assertOk();

    $this->assertDatabaseHas('notification_templates', [
        'event' => 'loan_disbursed',
        'channel' => 'email',
        'subject' => 'Your loan has been disbursed',
    ]);
})->group('notification-templates');

test('upsert rejects unknown event', function () {
    $admin = templateAdmin();

    $this->actingAs($admin)
        ->putJson(route('api.v1.notification-templates.upsert', ['event' => 'alien_event', 'channel' => 'sms']), [
            'body' => 'Some text',
        ])
        ->assertUnprocessable();
})->group('notification-templates');

test('upsert rejects unknown channel', function () {
    $admin = templateAdmin();

    $this->actingAs($admin)
        ->putJson(route('api.v1.notification-templates.upsert', ['event' => 'loan_approved', 'channel' => 'whatsapp']), [
            'body' => 'Some text',
        ])
        ->assertUnprocessable();
})->group('notification-templates');

test('upsert requires body', function () {
    $admin = templateAdmin();

    $this->actingAs($admin)
        ->putJson(route('api.v1.notification-templates.upsert', ['event' => 'loan_approved', 'channel' => 'sms']), [
            'name' => 'No body template',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);
})->group('notification-templates');

// ─── Delete ───────────────────────────────────────────────────────────────────

test('can delete a template', function () {
    $admin = templateAdmin();

    NotificationTemplate::create([
        'event' => 'welcome',
        'channel' => 'sms',
        'body' => 'Welcome {{borrower_name}}!',
    ]);

    $this->actingAs($admin)
        ->deleteJson(route('api.v1.notification-templates.destroy', ['event' => 'welcome', 'channel' => 'sms']))
        ->assertOk()
        ->assertJsonPath('message', 'Template deleted.');

    $this->assertDatabaseMissing('notification_templates', [
        'event' => 'welcome',
        'channel' => 'sms',
    ]);
})->group('notification-templates');

test('delete returns 404 for non-existent template', function () {
    $admin = templateAdmin();

    $this->actingAs($admin)
        ->deleteJson(route('api.v1.notification-templates.destroy', ['event' => 'loan_approved', 'channel' => 'sms']))
        ->assertNotFound();
})->group('notification-templates');

// ─── Preview ─────────────────────────────────────────────────────────────────

test('preview replaces placeholders with sample data', function () {
    $admin = templateAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.notification-templates.preview', ['event' => 'loan_approved', 'channel' => 'sms']), [
            'body' => 'Dear {{borrower_name}}, loan {{loan_number}} approved. Amount: {{amount}}.',
        ])
        ->assertOk()
        ->assertJsonPath('data.body', 'Dear John Doe, loan LN-202603-00001 approved. Amount: 5,000.00.');
})->group('notification-templates');

test('preview replaces subject placeholders too', function () {
    $admin = templateAdmin();

    $response = $this->actingAs($admin)
        ->postJson(route('api.v1.notification-templates.preview', ['event' => 'loan_disbursed', 'channel' => 'email']), [
            'subject' => 'Loan {{loan_number}} disbursed',
            'body' => 'Hello {{borrower_name}}',
        ])
        ->assertOk();

    expect($response->json('data.subject'))->toContain('LN-202603-00001');
    expect($response->json('data.body'))->toContain('John Doe');
})->group('notification-templates');

test('preview returns null subject when not provided', function () {
    $admin = templateAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.notification-templates.preview', ['event' => 'otp', 'channel' => 'sms']), [
            'body' => 'Your OTP is {{otp}}',
        ])
        ->assertOk()
        ->assertJsonPath('data.subject', null);
})->group('notification-templates');

test('preview requires body', function () {
    $admin = templateAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.notification-templates.preview', ['event' => 'otp', 'channel' => 'sms']), [
            'subject' => 'Test',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);
})->group('notification-templates');

// ─── Authorization ────────────────────────────────────────────────────────────

test('unauthenticated cannot access notification templates', function () {
    $this->getJson(route('api.v1.notification-templates.index'))
        ->assertUnauthorized();
})->group('notification-templates');
