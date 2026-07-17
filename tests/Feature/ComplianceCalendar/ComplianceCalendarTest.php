<?php

use App\Enums\UserRole;
use App\Models\Tenant\ComplianceEvent;
use App\Models\Tenant\User;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function calendarAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function makeEvent(array $attrs = []): ComplianceEvent
{
    return ComplianceEvent::create(array_merge([
        'title'     => 'Test Event ' . rand(100, 999),
        'category'  => 'regulatory',
        'due_date'  => now()->addDays(10)->toDateString(),
        'frequency' => 'once',
        'status'    => 'pending',
    ], $attrs));
}

// ─── CRUD ─────────────────────────────────────────────────────────────────────

test('can list compliance events', function () {
    $admin = calendarAdmin();
    makeEvent(['title' => 'BOZ Quarterly Report']);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.compliance-events.index'))
        ->assertOk();

    expect($resp->json('data'))->not->toBeEmpty();
});

test('can create a compliance event', function () {
    $admin = calendarAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.compliance-events.store'), [
            'title'    => 'Annual Audit',
            'category' => 'audit',
            'due_date' => now()->addMonths(2)->toDateString(),
            'frequency' => 'annually',
        ])
        ->assertCreated();

    expect($resp->json('data.event.title'))->toBe('Annual Audit')
        ->and($resp->json('data.event.status'))->toBe('pending');
});

test('can show a compliance event', function () {
    $admin = calendarAdmin();
    $event = makeEvent(['title' => 'Tax Filing Q1']);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.compliance-events.show', $event))
        ->assertOk();

    expect($resp->json('data.event.title'))->toBe('Tax Filing Q1');
});

test('can update a compliance event', function () {
    $admin = calendarAdmin();
    $event = makeEvent();

    $resp = $this->actingAs($admin)
        ->putJson(route('api.v1.compliance-events.update', $event), [
            'title' => 'Updated Title',
        ])
        ->assertOk();

    expect($resp->json('data.event.title'))->toBe('Updated Title');
});

test('can delete a compliance event', function () {
    $admin = calendarAdmin();
    $event = makeEvent();

    $this->actingAs($admin)
        ->deleteJson(route('api.v1.compliance-events.destroy', $event))
        ->assertOk();

    expect(ComplianceEvent::find($event->id))->toBeNull();
});

// ─── Complete Event ───────────────────────────────────────────────────────────

test('can complete a compliance event', function () {
    $admin = calendarAdmin();
    $event = makeEvent(['title' => 'Monthly Return']);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.compliance-events.complete', $event))
        ->assertOk();

    expect($resp->json('data.event.status'))->toBe('completed')
        ->and($resp->json('data.event.completed_by'))->toBe($admin->id);
});

test('completing a recurring event spawns a next occurrence', function () {
    $admin = calendarAdmin();
    $event = makeEvent([
        'title'     => 'Monthly Submission',
        'due_date'  => now()->subDays(5)->toDateString(),  // past
        'frequency' => 'monthly',
    ]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.compliance-events.complete', $event))
        ->assertOk();

    // A new pending event should have been created
    $next = ComplianceEvent::where('title', 'Monthly Submission')
        ->where('status', 'pending')
        ->first();

    expect($next)->not->toBeNull()
        ->and($next->due_date->greaterThan($event->due_date))->toBeTrue();
});

test('cannot complete an already completed event', function () {
    $admin = calendarAdmin();
    $event = makeEvent(['status' => 'completed']);

    $this->actingAs($admin)
        ->postJson(route('api.v1.compliance-events.complete', $event))
        ->assertUnprocessable();
});

// ─── Upcoming & Overdue ───────────────────────────────────────────────────────

test('upcoming endpoint returns events due within N days', function () {
    $admin = calendarAdmin();
    makeEvent(['due_date' => now()->addDays(5)->toDateString()]);
    makeEvent(['due_date' => now()->addDays(60)->toDateString()]); // too far

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.compliance-events.upcoming', ['days' => 30]))
        ->assertOk();

    $titles = collect($resp->json('data.events'))->pluck('due_date');
    foreach ($titles as $date) {
        expect(\Carbon\Carbon::parse($date)->lte(now()->addDays(30)))->toBeTrue();
    }
});

test('overdue events are auto-marked on list endpoint', function () {
    $admin = calendarAdmin();
    makeEvent(['due_date' => now()->subDays(3)->toDateString(), 'status' => 'pending']);

    $this->actingAs($admin)
        ->getJson(route('api.v1.compliance-events.index'))
        ->assertOk();

    $updated = ComplianceEvent::whereDate('due_date', now()->subDays(3)->toDateString())->first();
    expect($updated->status)->toBe('overdue');
});

test('compliance reminder command marks overdue and flags upcoming', function () {
    makeEvent(['due_date' => now()->subDay()->toDateString(), 'status' => 'pending']);   // overdue
    makeEvent(['due_date' => now()->addDays(5)->toDateString(), 'status' => 'pending']); // upcoming

    $this->artisan('lendr:compliance-reminders', ['--days' => 7])
        ->assertSuccessful();

    expect(ComplianceEvent::where('status', 'overdue')->count())->toBeGreaterThan(0);
    expect(ComplianceEvent::where('reminder_sent', true)->count())->toBeGreaterThan(0);
});

test('can filter events by category', function () {
    $admin = calendarAdmin();
    makeEvent(['category' => 'audit']);
    makeEvent(['category' => 'tax']);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.compliance-events.index', ['category' => 'audit']))
        ->assertOk();

    foreach ($resp->json('data') as $event) {
        expect($event['category'])->toBe('audit');
    }
});
