<?php

use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\User;
use Spatie\Activitylog\Models\Activity;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function auditAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function auditActivity(array $attrs = []): Activity
{
    return activity()
        ->log($attrs['description'] ?? 'created');
}

// ─── Index / List ─────────────────────────────────────────────────────────────

test('admin can view audit log', function () {
    $user = auditAdmin();
    auditActivity(['description' => 'created']);
    auditActivity(['description' => 'updated']);

    $this->actingAs($user)
        ->getJson(route('api.v1.audit-log.index'))
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
})->group('audit-log');

test('audit log is paginated', function () {
    $user = auditAdmin();

    // Create 35 entries — default per_page is 30
    for ($i = 0; $i < 35; $i++) {
        auditActivity();
    }

    $response = $this->actingAs($user)
        ->getJson(route('api.v1.audit-log.index'))
        ->assertOk();

    $this->assertCount(30, $response->json('data'));
    $this->assertGreaterThanOrEqual(35, $response->json('meta.total'));
})->group('audit-log');

test('audit log respects per_page parameter', function () {
    $user = auditAdmin();

    for ($i = 0; $i < 10; $i++) {
        auditActivity();
    }

    $response = $this->actingAs($user)
        ->getJson(route('api.v1.audit-log.index', ['per_page' => 5]))
        ->assertOk();

    $this->assertCount(5, $response->json('data'));
})->group('audit-log');

test('unauthenticated request to audit log is rejected', function () {
    $this->getJson(route('api.v1.audit-log.index'))
        ->assertUnauthorized();
})->group('audit-log');

// ─── Filter by subject_type ───────────────────────────────────────────────────

test('audit log filters by subject_type', function () {
    $user = auditAdmin();
    $borrower = Borrower::factory()->create();

    activity()->on($borrower)->log('created');
    activity()->log('some other event'); // no subject

    $response = $this->actingAs($user)
        ->getJson(route('api.v1.audit-log.index', ['subject_type' => 'Borrower']))
        ->assertOk();

    foreach ($response->json('data') as $entry) {
        $this->assertSame('Borrower', $entry['subject_type']);
    }
})->group('audit-log');

// ─── Filter by event / description ───────────────────────────────────────────

test('audit log filters by event description', function () {
    $user = auditAdmin();

    activity()->log('loan_created');
    activity()->log('payment_recorded');
    activity()->log('loan_created');

    $response = $this->actingAs($user)
        ->getJson(route('api.v1.audit-log.index', ['event' => 'loan_created']))
        ->assertOk();

    foreach ($response->json('data') as $entry) {
        $this->assertSame('loan_created', $entry['description']);
    }
})->group('audit-log');

// ─── Filter by causer_id ──────────────────────────────────────────────────────

test('audit log filters by causer_id', function () {
    $actor = auditAdmin();
    $other = auditAdmin();

    activity()->causedBy($actor)->log('did something');
    activity()->causedBy($other)->log('did something else');

    $response = $this->actingAs($actor)
        ->getJson(route('api.v1.audit-log.index', ['causer_id' => $actor->id]))
        ->assertOk();

    foreach ($response->json('data') as $entry) {
        $this->assertSame($actor->id, $entry['causer']['id']);
    }
})->group('audit-log');

// ─── Filter by date range ─────────────────────────────────────────────────────

test('audit log filters by date_from', function () {
    $user = auditAdmin();

    // Old entry
    $old = Activity::create([
        'log_name' => 'default',
        'description' => 'old event',
        'subject_type' => null,
        'subject_id' => null,
        'causer_type' => null,
        'causer_id' => null,
        'properties' => '{}',
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ]);

    activity()->log('new event'); // today

    $response = $this->actingAs($user)
        ->getJson(route('api.v1.audit-log.index', ['date_from' => now()->subDay()->toDateString()]))
        ->assertOk();

    $descriptions = collect($response->json('data'))->pluck('description');
    $this->assertContains('new event', $descriptions->all());
    $this->assertNotContains('old event', $descriptions->all());
})->group('audit-log');

// ─── CSV Export ───────────────────────────────────────────────────────────────

test('admin can export audit log as CSV', function () {
    $user = auditAdmin();
    activity()->log('exported event');

    $response = $this->actingAs($user)
        ->get(route('api.v1.audit-log.export'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $csv = $response->getContent();
    $this->assertStringContainsString('Date,Event,Subject,Subject ID,Performed By,Log Name', $csv);
    $this->assertStringContainsString('exported event', $csv);
})->group('audit-log');

test('CSV export returns all rows without pagination', function () {
    $user = auditAdmin();

    for ($i = 1; $i <= 35; $i++) {
        activity()->log("event_{$i}");
    }

    $response = $this->actingAs($user)
        ->get(route('api.v1.audit-log.export'))
        ->assertOk();

    $lines = array_filter(explode("\n", trim($response->getContent())));
    // Header + 35 data rows
    $this->assertGreaterThanOrEqual(36, count($lines));
})->group('audit-log');

test('CSV export respects event filter', function () {
    $user = auditAdmin();
    activity()->log('target_event');
    activity()->log('other_event');

    $response = $this->actingAs($user)
        ->get(route('api.v1.audit-log.export', ['event' => 'target_event']))
        ->assertOk();

    $csv = $response->getContent();
    $this->assertStringContainsString('target_event', $csv);
    $this->assertStringNotContainsString('other_event', $csv);
})->group('audit-log');

test('unauthenticated user cannot export audit log', function () {
    $this->get(route('api.v1.audit-log.export'))
        ->assertRedirect(); // redirects to login
})->group('audit-log');
