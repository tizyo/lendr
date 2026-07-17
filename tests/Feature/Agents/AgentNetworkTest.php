<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Agent;
use App\Models\Tenant\AgentCommission;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function agentAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function makeAgent(array $attrs = []): Agent
{
    return Agent::create(array_merge([
        'agent_number'    => Agent::generateAgentNumber(),
        'first_name'      => 'Test',
        'last_name'       => 'Agent',
        'phone'           => '0977'.rand(100000, 999999),
        'commission_rate' => 5.00,
        'commission_type' => 'percentage',
        'status'          => 'active',
    ], $attrs));
}

function makeAgentCommission(Agent $agent, Loan $loan, array $attrs = []): AgentCommission
{
    return AgentCommission::create(array_merge([
        'agent_id'         => $agent->id,
        'loan_id'          => $loan->id,
        'disbursed_amount' => 5000,
        'commission_amount' => 250,
        'status'           => 'pending',
    ], $attrs));
}

function agentLoan(): Loan
{
    $type     = LoanType::first() ?? LoanType::factory()->create();
    $plan     = LoanPlan::first() ?? LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create();

    return Loan::factory()->create([
        'borrower_id'  => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'status'       => LoanStatus::Active,
    ]);
}

// ─── Agent CRUD tests ─────────────────────────────────────────────────────────

test('can create an agent', function () {
    $admin = agentAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.agents.store'), [
            'first_name'      => 'David',
            'last_name'       => 'Banda',
            'phone'           => '0977010101',
            'commission_type' => 'percentage',
            'commission_rate' => 6.5,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.agent.first_name', 'David');

    $this->assertDatabaseHas('agents', ['phone' => '0977010101']);
})->group('agents');

test('agent number is auto-generated', function () {
    $admin = agentAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.agents.store'), ['first_name' => 'Eve', 'phone' => '0977020202'])
        ->assertStatus(201);

    expect($resp->json('data.agent.agent_number'))->toStartWith('AGT');
})->group('agents');

test('can list agents', function () {
    $admin = agentAdmin();
    makeAgent();

    $this->actingAs($admin)
        ->getJson(route('api.v1.agents.index'))
        ->assertOk()
        ->assertJsonStructure(['data' => ['data']]);
})->group('agents');

test('can show an agent', function () {
    $admin = agentAdmin();
    $agent = makeAgent();

    $this->actingAs($admin)
        ->getJson(route('api.v1.agents.show', $agent))
        ->assertOk()
        ->assertJsonPath('data.agent.agent_number', $agent->agent_number);
})->group('agents');

test('can update an agent', function () {
    $admin = agentAdmin();
    $agent = makeAgent();

    $this->actingAs($admin)
        ->putJson(route('api.v1.agents.update', $agent), ['status' => 'suspended'])
        ->assertOk()
        ->assertJsonPath('data.agent.status', 'suspended');
})->group('agents');

test('can delete an agent', function () {
    $admin = agentAdmin();
    $agent = makeAgent();

    $this->actingAs($admin)
        ->deleteJson(route('api.v1.agents.destroy', $agent))
        ->assertStatus(204);

    $this->assertSoftDeleted('agents', ['id' => $agent->id]);
})->group('agents');

test('can calculate percentage commission correctly', function () {
    $agent = makeAgent(['commission_rate' => 5.0, 'commission_type' => 'percentage']);
    expect($agent->calculateCommission(10000))->toBe(500.0);
})->group('agents');

test('can calculate fixed commission correctly', function () {
    $agent = makeAgent(['commission_type' => 'fixed', 'fixed_commission' => 250.00]);
    expect($agent->calculateCommission(99999))->toBe(250.0);
})->group('agents');

// ─── Commission tests ─────────────────────────────────────────────────────────

test('can list all commissions', function () {
    $admin = agentAdmin();
    $agent = makeAgent();
    $loan  = agentLoan();
    makeAgentCommission($agent, $loan);

    $this->actingAs($admin)
        ->getJson(route('api.v1.agents.commissions.all'))
        ->assertOk()
        ->assertJsonStructure(['data' => ['data']]);
})->group('agents');

test('can approve a pending commission', function () {
    $admin      = agentAdmin();
    $agent      = makeAgent();
    $loan       = agentLoan();
    $commission = makeAgentCommission($agent, $loan);

    $this->actingAs($admin)
        ->postJson(route('api.v1.agent-commissions.approve', $commission))
        ->assertOk()
        ->assertJsonPath('data.commission.status', 'approved');
})->group('agents');

test('cannot approve a non-pending commission', function () {
    $admin      = agentAdmin();
    $agent      = makeAgent();
    $loan       = agentLoan();
    $commission = makeAgentCommission($agent, $loan, ['status' => 'paid']);

    $this->actingAs($admin)
        ->postJson(route('api.v1.agent-commissions.approve', $commission))
        ->assertStatus(422);
})->group('agents');

test('can pay an approved commission', function () {
    $admin      = agentAdmin();
    $agent      = makeAgent();
    $loan       = agentLoan();
    $commission = makeAgentCommission($agent, $loan, ['status' => 'approved']);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.agent-commissions.pay', $commission), [
            'paid_date'         => now()->toDateString(),
            'payment_reference' => 'PAY-REF-001',
        ])
        ->assertOk()
        ->assertJsonPath('data.commission.status', 'paid');

    expect($resp->json('data.commission.paid_date'))->not->toBeNull();
})->group('agents');

test('cannot pay a non-approved commission', function () {
    $admin      = agentAdmin();
    $agent      = makeAgent();
    $loan       = agentLoan();
    $commission = makeAgentCommission($agent, $loan, ['status' => 'pending']);

    $this->actingAs($admin)
        ->postJson(route('api.v1.agent-commissions.pay', $commission), ['paid_date' => now()->toDateString()])
        ->assertStatus(422);
})->group('agents');

test('can view commissions for a specific agent', function () {
    $admin      = agentAdmin();
    $agent      = makeAgent();
    $loan       = agentLoan();
    makeAgentCommission($agent, $loan);

    $this->actingAs($admin)
        ->getJson(route('api.v1.agents.commissions', $agent))
        ->assertOk()
        ->assertJsonStructure(['data' => ['data']]);
})->group('agents');

test('guest cannot access agents', function () {
    $this->getJson(route('api.v1.agents.index'))
        ->assertUnauthorized();
})->group('agents');
