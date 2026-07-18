<?php

use App\Models\Landlord\LandlordUser;
use App\Models\Landlord\SupportTicket;
use App\Models\Landlord\SupportTicketReply;
use App\Models\Landlord\Tenant;

// ─── Cleanup ──────────────────────────────────────────────────────────────────
afterEach(function () {
    SupportTicketReply::query()->delete();
    SupportTicket::query()->delete();
    Tenant::query()->delete();
});

// ─── Helpers ──────────────────────────────────────────────────────────────────
function makeSupportTenant(): Tenant
{
    return Tenant::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'name' => 'Support MFI '.uniqid(),
        'slug' => 'support-mfi-'.uniqid(),
        'plan' => 'starter',
        'status' => 'active',
        'currency' => 'ZMW',
        'timezone' => 'Africa/Lusaka',
    ]);
}

function makeTicket(Tenant $tenant, array $overrides = []): SupportTicket
{
    return SupportTicket::create(array_merge([
        'tenant_id' => $tenant->id,
        'subject' => 'Test issue '.uniqid(),
        'message' => 'Detailed description of the issue.',
        'type' => 'support',
        'status' => 'open',
        'priority' => 'medium',
        'submitted_by' => 'Test User',
        'submitted_by_email' => 'user@test.com',
    ], $overrides));
}

// ─── SupportTicket model ──────────────────────────────────────────────────────

it('creates a support ticket with correct defaults', function () {
    $tenant = makeSupportTenant();
    $ticket = makeTicket($tenant);

    expect($ticket->status)->toBe('open');
    expect($ticket->priority)->toBe('medium');
    expect($ticket->type)->toBe('support');
    expect($ticket->isOpen())->toBeTrue();
});

it('isOpen returns false for resolved ticket', function () {
    $tenant = makeSupportTenant();
    $ticket = makeTicket($tenant, ['status' => 'resolved']);

    expect($ticket->isOpen())->toBeFalse();
});

it('typeBadge returns correct labels', function () {
    $tenant = makeSupportTenant();

    expect(makeTicket($tenant, ['type' => 'bug'])->typeBadge())->toBe('Bug');
    expect(makeTicket($tenant, ['type' => 'feature'])->typeBadge())->toBe('Feature Request');
    expect(makeTicket($tenant, ['type' => 'support'])->typeBadge())->toBe('Support');
});

// ─── Landlord API: list ───────────────────────────────────────────────────────

it('landlord can list all support tickets', function () {
    $tenant = makeSupportTenant();
    makeTicket($tenant, ['type' => 'bug']);
    makeTicket($tenant, ['type' => 'feature']);

    $landlord = LandlordUser::factory()->create();

    $response = $this->actingAs($landlord, 'sanctum')
        ->getJson('/api/v1/landlord/support');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data' => [['id', 'subject', 'type', 'status', 'priority', 'tenant_name', 'created_at']],
            ],
        ]);

    expect($response->json('data.data'))->toHaveCount(2);
});

it('landlord can filter tickets by status', function () {
    $tenant = makeSupportTenant();
    makeTicket($tenant, ['status' => 'open']);
    makeTicket($tenant, ['status' => 'resolved']);

    $landlord = LandlordUser::factory()->create();

    $response = $this->actingAs($landlord, 'sanctum')
        ->getJson('/api/v1/landlord/support?status=open');

    $response->assertOk();
    $tickets = $response->json('data.data');
    expect(collect($tickets)->every(fn ($t) => $t['status'] === 'open'))->toBeTrue();
});

it('landlord can filter tickets by type', function () {
    $tenant = makeSupportTenant();
    makeTicket($tenant, ['type' => 'bug']);
    makeTicket($tenant, ['type' => 'support']);

    $landlord = LandlordUser::factory()->create();

    $data = $this->actingAs($landlord, 'sanctum')
        ->getJson('/api/v1/landlord/support?type=bug')
        ->assertOk()
        ->json('data.data');

    expect(collect($data)->every(fn ($t) => $t['type'] === 'bug'))->toBeTrue();
});

// ─── Landlord API: show ───────────────────────────────────────────────────────

it('landlord can view a ticket with replies', function () {
    $tenant = makeSupportTenant();
    $ticket = makeTicket($tenant);
    SupportTicketReply::create([
        'ticket_id' => $ticket->id,
        'author_type' => 'tenant',
        'author_name' => 'Test User',
        'message' => 'Here is more context.',
    ]);

    $landlord = LandlordUser::factory()->create();

    $response = $this->actingAs($landlord, 'sanctum')
        ->getJson("/api/v1/landlord/support/{$ticket->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $ticket->id)
        ->assertJsonStructure(['data' => ['replies' => [['id', 'author_type', 'message']]]]);
});

// ─── Landlord API: reply ──────────────────────────────────────────────────────

it('landlord can reply to a ticket', function () {
    $tenant = makeSupportTenant();
    $ticket = makeTicket($tenant);

    $landlord = LandlordUser::factory()->create();

    $response = $this->actingAs($landlord, 'sanctum')
        ->postJson("/api/v1/landlord/support/{$ticket->id}/reply", [
            'message' => 'We are looking into this.',
        ]);

    $response->assertOk();

    expect(SupportTicketReply::where('ticket_id', $ticket->id)->count())->toBe(1);
    expect(SupportTicketReply::where('ticket_id', $ticket->id)->value('author_type'))->toBe('landlord');
});

it('landlord reply auto-moves open ticket to in_progress', function () {
    $tenant = makeSupportTenant();
    $ticket = makeTicket($tenant, ['status' => 'open']);

    $landlord = LandlordUser::factory()->create();

    $this->actingAs($landlord, 'sanctum')
        ->postJson("/api/v1/landlord/support/{$ticket->id}/reply", [
            'message' => 'Working on it.',
        ]);

    expect($ticket->fresh()->status)->toBe('in_progress');
});

// ─── Landlord API: status update ──────────────────────────────────────────────

it('landlord can update ticket status', function () {
    $tenant = makeSupportTenant();
    $ticket = makeTicket($tenant);

    $landlord = LandlordUser::factory()->create();

    $this->actingAs($landlord, 'sanctum')
        ->patchJson("/api/v1/landlord/support/{$ticket->id}/status", ['status' => 'resolved'])
        ->assertOk();

    $ticket->refresh();
    expect($ticket->status)->toBe('resolved');
    expect($ticket->resolved_at)->not->toBeNull();
});

it('landlord can update ticket priority', function () {
    $tenant = makeSupportTenant();
    $ticket = makeTicket($tenant, ['priority' => 'low']);

    $landlord = LandlordUser::factory()->create();

    $this->actingAs($landlord, 'sanctum')
        ->patchJson("/api/v1/landlord/support/{$ticket->id}/priority", ['priority' => 'critical'])
        ->assertOk();

    expect($ticket->fresh()->priority)->toBe('critical');
});

// ─── Landlord API: stats ──────────────────────────────────────────────────────

it('landlord can fetch support stats', function () {
    $tenant = makeSupportTenant();
    makeTicket($tenant, ['status' => 'open',     'type' => 'bug']);
    makeTicket($tenant, ['status' => 'resolved', 'type' => 'support']);
    makeTicket($tenant, ['status' => 'open',     'type' => 'feature']);

    $landlord = LandlordUser::factory()->create();

    $data = $this->actingAs($landlord, 'sanctum')
        ->getJson('/api/v1/landlord/support/stats')
        ->assertOk()
        ->json('data');

    expect($data['total'])->toBe(3);
    expect($data['open'])->toBe(2);
    expect($data['by_status']['open'])->toBe(2);
    expect($data['by_status']['resolved'])->toBe(1);
});

// ─── Validation ───────────────────────────────────────────────────────────────

it('landlord reply requires a message', function () {
    $tenant = makeSupportTenant();
    $ticket = makeTicket($tenant);
    $landlord = LandlordUser::factory()->create();

    $this->actingAs($landlord, 'sanctum')
        ->postJson("/api/v1/landlord/support/{$ticket->id}/reply", [])
        ->assertStatus(422);
});

it('landlord status update rejects invalid status', function () {
    $tenant = makeSupportTenant();
    $ticket = makeTicket($tenant);
    $landlord = LandlordUser::factory()->create();

    $this->actingAs($landlord, 'sanctum')
        ->patchJson("/api/v1/landlord/support/{$ticket->id}/status", ['status' => 'pending'])
        ->assertStatus(422);
});
