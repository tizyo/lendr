<?php

use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\BorrowerInteraction;
use App\Models\Tenant\Lead;
use App\Models\Tenant\User;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function crmAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function makeLead(array $attrs = []): Lead
{
    return Lead::create(array_merge([
        'lead_number' => Lead::generateLeadNumber(),
        'first_name'  => 'Jane',
        'last_name'   => 'Doe',
        'phone'       => '0977000001',
        'status'      => 'new',
    ], $attrs));
}

// ─── Lead CRUD tests ──────────────────────────────────────────────────────────

test('can create a lead', function () {
    $admin = crmAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.leads.store'), [
            'first_name' => 'Alice',
            'last_name'  => 'Mutale',
            'phone'      => '0977111222',
            'source'     => 'referral',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.lead.first_name', 'Alice');

    $this->assertDatabaseHas('leads', ['phone' => '0977111222']);
})->group('crm');

test('lead number is auto-generated', function () {
    $admin = crmAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.leads.store'), ['first_name' => 'Bob', 'phone' => '0977222333'])
        ->assertStatus(201);

    expect($resp->json('data.lead.lead_number'))->toStartWith('LD');
})->group('crm');

test('can list leads', function () {
    $admin = crmAdmin();
    makeLead(['first_name' => 'Charlie']);

    $this->actingAs($admin)
        ->getJson(route('api.v1.leads.index'))
        ->assertOk()
        ->assertJsonStructure(['data' => ['data']]);
})->group('crm');

test('can filter leads by status', function () {
    $admin = crmAdmin();
    makeLead(['status' => 'new', 'phone' => '0977100001']);
    makeLead(['status' => 'qualified', 'phone' => '0977100002', 'lead_number' => 'LD000002']);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.leads.index', ['status' => 'qualified']))
        ->assertOk();

    $statuses = collect($resp->json('data.data'))->pluck('status')->unique()->values()->toArray();
    expect($statuses)->toContain('qualified');
})->group('crm');

test('can update a lead', function () {
    $admin = crmAdmin();
    $lead  = makeLead();

    $this->actingAs($admin)
        ->putJson(route('api.v1.leads.update', $lead), ['status' => 'contacted', 'notes' => 'Called today'])
        ->assertOk()
        ->assertJsonPath('data.lead.status', 'contacted');
})->group('crm');

test('can delete a lead', function () {
    $admin = crmAdmin();
    $lead  = makeLead(['phone' => '0977500001']);

    $this->actingAs($admin)
        ->deleteJson(route('api.v1.leads.destroy', $lead))
        ->assertStatus(204);

    $this->assertSoftDeleted('leads', ['id' => $lead->id]);
})->group('crm');

test('can show lead pipeline summary', function () {
    $admin = crmAdmin();
    makeLead(['status' => 'new']);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.leads.pipeline'))
        ->assertOk();

    expect($resp->json('data'))->toHaveKey('new');
})->group('crm');

test('can convert a lead to borrower', function () {
    $admin = crmAdmin();
    $lead  = makeLead(['phone' => '0977600001']);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.leads.convert', $lead), [
            'first_name' => $lead->first_name,
            'phone'      => $lead->phone,
        ])
        ->assertOk()
        ->assertJsonStructure(['data' => ['lead', 'borrower']]);

    expect($resp->json('data.lead.status'))->toBe('converted');
    $this->assertDatabaseHas('borrowers', ['phone' => '0977600001']);
})->group('crm');

test('cannot convert an already-converted lead', function () {
    $admin    = crmAdmin();
    $borrower = Borrower::factory()->create();
    $lead     = makeLead(['status' => 'converted', 'converted_borrower_id' => $borrower->id, 'phone' => '0977700001']);

    $this->actingAs($admin)
        ->postJson(route('api.v1.leads.convert', $lead), ['first_name' => 'Jane', 'phone' => $lead->phone])
        ->assertStatus(422);
})->group('crm');

test('can add interaction to a lead', function () {
    $admin = crmAdmin();
    $lead  = makeLead(['phone' => '0977800001']);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.leads.interactions.store', $lead), [
            'channel'   => 'call',
            'direction' => 'outbound',
            'outcome'   => 'spoke_to_borrower',
            'notes'     => 'Interested in 5000 loan',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.interaction.channel', 'call');

    $this->assertDatabaseHas('borrower_interactions', ['lead_id' => $lead->id]);
})->group('crm');

test('guest cannot access leads', function () {
    $this->getJson(route('api.v1.leads.index'))
        ->assertUnauthorized();
})->group('crm');
