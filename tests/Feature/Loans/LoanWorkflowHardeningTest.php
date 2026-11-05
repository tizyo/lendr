<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Jobs\SendLoanEventNotificationJob;
use App\Models\Tenant\Agent;
use App\Models\Tenant\AgentCommission;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\DisbursementLog;
use App\Models\Tenant\FundBalance;
use App\Models\Tenant\FundTransaction;
use App\Models\Tenant\GlJournalEntry;
use App\Models\Tenant\InAppNotification;
use App\Models\Tenant\InsuranceClaim;
use App\Models\Tenant\InsuranceProduct;
use App\Models\Tenant\Lead;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanAgreement;
use App\Models\Tenant\LoanInsurance;
use App\Models\Tenant\NotificationTemplate;
use App\Models\Tenant\RiskFlag;
use App\Models\Tenant\RiskPolicy;
use App\Models\Tenant\User;
use App\Services\LoanLifecycleService;
use App\Services\Mail\TenantMailService;
use App\Services\Payment\DisbursementOutcomeService;
use App\Services\SMS\SmsService;
use Laravel\Sanctum\Sanctum;

/*
 * Loan applications respect their plan, agents earn their commission, risk
 * policies actually stop an approval, agreements are signed with a code the
 * borrower receives, and the workspace's own message wording is used.
 */

beforeEach(function () {
    $this->admin = actingAsAdmin();
    $this->actingAs($this->admin);
});

// ─── Plan limits ─────────────────────────────────────────────────────────────

it('refuses an application outside its plan\'s amount and tenure, on screen and over the API', function () {
    $plan = makePlan(['min_amount' => 1000, 'max_amount' => 50000, 'min_tenure' => 1, 'max_tenure' => 12]);
    $borrower = Borrower::factory()->create();
    $application = [
        'borrower_id' => $borrower->id, 'loan_type_id' => $plan->loan_type_id, 'loan_plan_id' => $plan->id,
        'principal_amount' => 90000, 'tenure' => 24, 'application_date' => now()->toDateString(),
    ];

    $this->post(route('loans.store'), $application)->assertSessionHasErrors(['principal_amount', 'tenure']);

    Sanctum::actingAs($this->admin);
    $this->postJson(route('api.v1.loans.store'), $application)->assertUnprocessable()->assertJsonValidationErrors(['principal_amount', 'tenure']);

    $otherType = makePlan();
    $this->postJson(route('api.v1.loans.store'), [...$application, 'principal_amount' => 5000, 'tenure' => 6, 'loan_type_id' => $otherType->loan_type_id])
        ->assertUnprocessable()->assertJsonValidationErrors('loan_plan_id');

    $this->postJson(route('api.v1.loans.store'), [...$application, 'principal_amount' => 5000, 'tenure' => 6])->assertCreated();
    expect(Loan::count())->toBe(1);
});

// ─── Agent commissions ───────────────────────────────────────────────────────

function commissionedLoan(Agent $agent): Loan
{
    return Loan::factory()->approved()->create(['loan_plan_id' => makePlan()->id, 'agent_id' => $agent->id, 'principal_amount' => 5000]);
}

function activeAgent(array $attrs = []): Agent
{
    return Agent::create(['agent_number' => Agent::generateAgentNumber(), 'first_name' => 'Bwalya', 'phone' => '097'.random_int(1000000, 9999999),
        'commission_type' => 'percentage', 'commission_rate' => 2, 'status' => 'active', ...$attrs]);
}

it('accrues the agent\'s commission when the loan is disbursed, once', function () {
    fundTheBook();
    $agent = activeAgent();
    $loan = commissionedLoan($agent);

    app(LoanLifecycleService::class)->disburse($loan, $this->admin, ['disbursement_method' => 'cash', 'disbursement_date' => now()->toDateString()]);

    $commission = AgentCommission::where('loan_id', $loan->id)->sole();
    expect((float) $commission->commission_amount)->toBe(100.0)
        ->and($commission->status)->toBe('pending')
        ->and($commission->agent_id)->toBe($agent->id);
});

it('takes the commission back when the mobile-money payout fails', function () {
    fundTheBook();
    $loan = commissionedLoan(activeAgent());
    app(LoanLifecycleService::class)->disburse($loan, $this->admin, ['disbursement_method' => 'cash', 'disbursement_date' => now()->toDateString()]);
    $log = DisbursementLog::create(['loan_id' => $loan->id, 'gateway' => 'airtel_money', 'reference' => 'D-1', 'amount' => 5000, 'status' => 'initiated', 'used_wallet' => false]);

    app(DisbursementOutcomeService::class)->rejected($loan->fresh(), $log, 'Number not registered');

    expect(AgentCommission::where('loan_id', $loan->id)->value('status'))->toBe('reversed');
});

it('lets a commission be approved and paid once each', function () {
    fundTheBook(1000);
    $commission = AgentCommission::create(['agent_id' => activeAgent()->id, 'loan_id' => commissionedLoan(activeAgent(['phone' => '0971112233']))->id,
        'disbursed_amount' => 5000, 'commission_amount' => 100, 'status' => 'pending']);
    Sanctum::actingAs($this->admin);

    $this->postJson(route('api.v1.agent-commissions.pay', $commission), ['paid_date' => now()->toDateString()])->assertUnprocessable();
    $this->postJson(route('api.v1.agent-commissions.approve', $commission))->assertOk();
    $this->postJson(route('api.v1.agent-commissions.approve', $commission))->assertUnprocessable();
    $this->postJson(route('api.v1.agent-commissions.pay', $commission), ['paid_date' => now()->addDay()->toDateString()])->assertUnprocessable();
    $this->postJson(route('api.v1.agent-commissions.pay', $commission), ['paid_date' => now()->toDateString()])->assertOk();
    $this->postJson(route('api.v1.agent-commissions.pay', $commission), ['paid_date' => now()->toDateString()])->assertUnprocessable();

    expect($commission->fresh()->status)->toBe('paid')
        // Paid once: one debit from the fund, one journal entry.
        ->and(FundTransaction::where('type', 'commission')->count())->toBe(1)
        ->and(GlJournalEntry::where('source_type', AgentCommission::class)->count())->toBe(1);
});

it('pays a commission out of the fund and posts it to the ledger', function () {
    fundTheBook(1000);
    $commission = AgentCommission::create(['agent_id' => activeAgent()->id, 'loan_id' => commissionedLoan(activeAgent(['phone' => '0971112244']))->id,
        'disbursed_amount' => 5000, 'commission_amount' => 150, 'status' => 'approved']);
    Sanctum::actingAs($this->admin);

    $this->postJson(route('api.v1.agent-commissions.pay', $commission), ['paid_date' => now()->subDays(2)->toDateString(), 'payment_reference' => 'MM-778'])
        ->assertOk();

    $fund = FundBalance::current()->fresh();
    expect((float) $fund->available_balance)->toBe(850.0)
        ->and((float) $fund->total_expenses)->toBe(150.0); // so the fund still reconciles

    $entry = GlJournalEntry::with('lines.account')->where('source_type', AgentCommission::class)->where('source_id', $commission->id)->sole();
    expect($entry->entry_date->toDateString())->toBe(now()->subDays(2)->toDateString())
        ->and($entry->isBalanced())->toBeTrue()
        ->and($entry->lines->firstWhere('side', 'debit')->account->code)->toBe('5004')
        ->and((float) $entry->lines->firstWhere('side', 'debit')->amount)->toBe(150.0)
        ->and($entry->lines->firstWhere('side', 'credit')->account->code)->toBe('1001');
});

it('refuses to pay a commission the fund cannot cover, and changes nothing', function () {
    fundTheBook(50);
    $commission = AgentCommission::create(['agent_id' => activeAgent()->id, 'loan_id' => commissionedLoan(activeAgent(['phone' => '0971112255']))->id,
        'disbursed_amount' => 5000, 'commission_amount' => 100, 'status' => 'approved']);
    Sanctum::actingAs($this->admin);

    $this->postJson(route('api.v1.agent-commissions.pay', $commission), ['paid_date' => now()->toDateString()])
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'Not enough in the fund to pay this commission: K 100.00 needed, K 50.00 available. Record a fund deposit first (Funding → Fund Management).']);

    expect($commission->fresh()->status)->toBe('approved')
        ->and((float) FundBalance::current()->fresh()->available_balance)->toBe(50.0)
        ->and(GlJournalEntry::where('source_type', AgentCommission::class)->exists())->toBeFalse();
});

// ─── Risk policies ───────────────────────────────────────────────────────────

it('stops an approval that breaks a blocking risk policy until someone overrides it', function () {
    $borrower = Borrower::factory()->create(['credit_score' => 400]);
    $loan = Loan::factory()->submitted()->create(['borrower_id' => $borrower->id, 'loan_plan_id' => makePlan()->id]);
    RiskPolicy::create(['name' => 'Minimum score', 'rule_type' => 'min_credit_score', 'value' => '600', 'action' => 'block', 'is_active' => true]);
    RiskPolicy::create(['name' => 'Big loans', 'rule_type' => 'max_loan_amount', 'value' => '1', 'action' => 'warn', 'is_active' => true]);
    Sanctum::actingAs($this->admin);

    $refused = $this->postJson(route('api.v1.loans.approve', $loan))->assertStatus(409);
    expect($refused->json('risk_flags'))->toHaveCount(1)
        ->and($loan->fresh()->status)->toBe(LoanStatus::Submitted);

    $this->postJson(route('api.v1.risk-flags.override', $refused->json('risk_flags.0.id')), ['override_reason' => 'Salary confirmed by employer'])->assertOk();

    $this->postJson(route('api.v1.loans.approve', $loan))->assertOk();
    expect($loan->fresh()->status)->toBe(LoanStatus::Approved)
        ->and(RiskFlag::where('loan_id', $loan->id)->where('severity', 'warn')->exists())->toBeTrue();
});

// ─── E-signature ─────────────────────────────────────────────────────────────

it('texts the signing code to the borrower and lets it sign the agreement', function () {
    $loan = Loan::factory()->approved()->create(['borrower_id' => Borrower::factory()->create(['phone' => '0971234567'])->id]);
    LoanAgreement::create(['loan_id' => $loan->id, 'document_hash' => str_repeat('a', 64), 'pdf_path' => 'agreements/x.pdf', 'status' => 'pending']);

    $sent = [];
    $sms = Mockery::mock(SmsService::class);
    $sms->shouldReceive('send')->andReturnUsing(function ($phone, $message) use (&$sent) {
        $sent[] = [$phone, $message];

        return true;
    });
    app()->instance(SmsService::class, $sms);
    Sanctum::actingAs($this->admin);

    $otp = $this->postJson(route('api.v1.loans.agreement.send-otp', $loan))->assertOk()->json('data.otp');

    expect($sent)->toHaveCount(1)
        ->and($sent[0][0])->toBe('0971234567')
        ->and($sent[0][1])->toContain($otp);

    // A second code straight away is refused (no spraying codes at a phone).
    $this->postJson(route('api.v1.loans.agreement.send-otp', $loan))->assertUnprocessable();

    $this->postJson(route('api.v1.loans.agreement.sign', $loan), ['otp' => $otp])->assertOk();
    expect(LoanAgreement::where('loan_id', $loan->id)->value('status'))->toBe('signed');
});

it('never hands the code back outside a developer machine', function () {
    app()->detectEnvironment(fn () => 'production');
    $loan = Loan::factory()->approved()->create(['borrower_id' => Borrower::factory()->create(['phone' => '0971234567'])->id]);
    LoanAgreement::create(['loan_id' => $loan->id, 'document_hash' => str_repeat('b', 64), 'pdf_path' => 'agreements/y.pdf', 'status' => 'pending']);
    app()->instance(SmsService::class, Mockery::mock(SmsService::class, ['send' => true]));
    Sanctum::actingAs($this->admin);

    $this->postJson(route('api.v1.loans.agreement.send-otp', $loan))->assertOk()->assertJsonPath('data.otp', null);

    app()->detectEnvironment(fn () => 'testing');
});

// ─── Message templates ───────────────────────────────────────────────────────

it('uses the workspace\'s own SMS wording for loan events', function () {
    $loan = Loan::factory()->approved()->create(['borrower_id' => Borrower::factory()->create(['first_name' => 'Natasha', 'last_name' => 'Phiri', 'phone' => '0961112222', 'email' => null])->id]);
    NotificationTemplate::create(['event' => 'loan_approved', 'channel' => 'sms', 'body' => 'Dear {{borrower_name}}, loan {{loan_number}} is approved.', 'is_active' => true]);

    $sms = Mockery::mock(SmsService::class);
    $sms->shouldReceive('send')->once()->with('0961112222', "Dear Natasha Phiri, loan {$loan->loan_number} is approved.")->andReturn(true);

    (new SendLoanEventNotificationJob($loan->id, 'approved'))->handle($sms, app(TenantMailService::class));
});

it('falls back to the built-in wording when the template is switched off', function () {
    $loan = Loan::factory()->approved()->create(['borrower_id' => Borrower::factory()->create(['first_name' => 'Natasha', 'phone' => '0961112222', 'email' => null])->id]);
    NotificationTemplate::create(['event' => 'loan_approved', 'channel' => 'sms', 'body' => 'Custom text', 'is_active' => false]);

    $sms = Mockery::mock(SmsService::class);
    $sms->shouldReceive('send')->once()->withArgs(fn ($phone, $message) => str_contains($message, 'has been approved'))->andReturn(true);

    (new SendLoanEventNotificationJob($loan->id, 'approved'))->handle($sms, app(TenantMailService::class));
});

// ─── CRM follow-ups and insurance claims ─────────────────────────────────────

it('actually reminds staff of leads due for follow-up', function () {
    $officer = User::factory()->create(['role' => UserRole::LoanOfficer, 'is_active' => true]);
    Lead::create(['lead_number' => Lead::generateLeadNumber(), 'first_name' => 'Chileshe', 'last_name' => 'Mumba', 'phone' => '0977000111',
        'source' => Lead::sources()[0], 'status' => 'contacted', 'assigned_to' => $officer->id, 'follow_up_date' => now()->subDay()->toDateString()]);

    $this->artisan('lendr:crm-followups')->assertSuccessful();

    expect(InAppNotification::where('user_id', $officer->id)->where('type', 'lead_followup')->count())->toBe(1);
});

it('lists every insurance claim with its policy number', function () {
    $loan = Loan::factory()->active()->create();
    $product = InsuranceProduct::create(['name' => 'Credit Life', 'code' => 'CL-01', 'premium_type' => 'percentage', 'premium_rate' => 1, 'coverage_type' => 'credit_life', 'is_active' => true]);
    $policy = LoanInsurance::create(['loan_id' => $loan->id, 'insurance_product_id' => $product->id, 'recorded_by' => $this->admin->id,
        'policy_number' => 'POL-778', 'sum_insured' => 5000, 'premium_amount' => 50, 'start_date' => now()->toDateString(), 'status' => 'active']);
    InsuranceClaim::create(['loan_insurance_id' => $policy->id, 'recorded_by' => $this->admin->id, 'claim_number' => 'CLM-1',
        'claim_type' => 'death', 'claim_amount' => 5000, 'status' => 'pending', 'incident_date' => now()->toDateString()]);
    Sanctum::actingAs($this->admin);

    $this->getJson(route('api.v1.insurance.claims.index'))
        ->assertOk()
        ->assertJsonPath('data.data.0.policy_number', 'POL-778')
        ->assertJsonPath('data.data.0.loan_id', $loan->id);
});
