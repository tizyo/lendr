<?php

use App\Enums\UserRole;
use App\Models\Landlord\CrbIdentity;
use App\Models\Landlord\CrbScoreEvent;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\User;
use App\Services\CrbService;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function crbAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function crbBorrower(array $extra = []): Borrower
{
    return Borrower::factory()->create(array_merge([
        'national_id' => 'NRC' . fake()->unique()->numerify('######/##/1'),
        'is_active'   => true,
    ], $extra));
}

function crbService(): CrbService
{
    return app(CrbService::class);
}

// ─── Hash tests ───────────────────────────────────────────────────────────────

test('hash is deterministic and type-scoped', function () {
    $crb = crbService();

    $h1 = $crb->hash('123456/78/1', 'nrc');
    $h2 = $crb->hash('123456/78/1', 'nrc');
    $h3 = $crb->hash('123456/78/1', 'tpin');

    expect($h1)->toBe($h2);           // same input → same hash
    expect($h1)->not->toBe($h3);      // different type → different hash
    expect(strlen($h1))->toBe(64);    // SHA-256 hex = 64 chars
});

test('hash is case-insensitive and trims whitespace', function () {
    $crb = crbService();

    $h1 = $crb->hash('  ABC/123 ', 'NRC');
    $h2 = $crb->hash('abc/123', 'nrc');

    expect($h1)->toBe($h2);
});

// ─── Check tests ──────────────────────────────────────────────────────────────

test('check on unknown hash returns safe defaults', function () {
    $crb    = crbService();
    $hash   = $crb->hash('999999/00/1', 'nrc');
    $result = $crb->check($hash, 'nrc', 'tenant-1', 'manual_check');

    expect($result['found'])->toBeFalse()
        ->and($result['credit_score'])->toBe(600)
        ->and($result['score_band'])->toBe('fair')
        ->and($result['has_active_loans'])->toBeFalse()
        ->and($result['active_loan_count'])->toBe(0);
});

test('check creates an inquiry log entry', function () {
    $crb  = crbService();
    $hash = $crb->hash('111111/01/1', 'nrc');
    $crb->check($hash, 'nrc', 'tenant-A', 'manual_check');

    $inquiry = \App\Models\Landlord\CrbInquiry::where('identity_hash', $hash)->first();
    expect($inquiry)->not->toBeNull()
        ->and($inquiry->tenant_id)->toBe('tenant-A')
        ->and($inquiry->purpose)->toBe('manual_check');
});

// ─── Score event tests ────────────────────────────────────────────────────────

test('recordLoanOpened creates identity and applies loan_opened event', function () {
    $crb  = crbService();
    $hash = $crb->hash('200001/01/1', 'nrc');

    $crb->recordLoanOpened($hash, 'nrc', 'tenant-1', 5000.0, 'LN-0001');

    $identity = CrbIdentity::where('identity_hash', $hash)->first();
    expect($identity)->not->toBeNull()
        ->and($identity->total_loans_taken)->toBe(1)
        ->and($identity->active_loan_count)->toBe(1)
        ->and($identity->first_loan_date)->not->toBeNull();

    // loan_opened = -5 points from default 600
    expect($identity->credit_score)->toBe(595);
});

test('multiple_loans_penalty fires when a second loan is opened', function () {
    $crb  = crbService();
    $hash = $crb->hash('200002/02/1', 'nrc');

    $crb->recordLoanOpened($hash, 'nrc', 'tenant-1', 5000.0, 'LN-0002');
    $crb->recordLoanOpened($hash, 'nrc', 'tenant-1', 3000.0, 'LN-0003');

    $identity = CrbIdentity::where('identity_hash', $hash)->first();
    expect($identity->active_loan_count)->toBe(2);

    // Events: loan_opened(-5) + loan_opened(-5) + multiple_loans_penalty(-15) = 600 - 25 = 575
    expect($identity->credit_score)->toBe(575);
});

test('early repayment raises credit score', function () {
    $crb  = crbService();
    $hash = $crb->hash('300001/01/1', 'nrc');

    $crb->recordLoanOpened($hash, 'nrc', 'tenant-1', 2000.0, 'LN-EARLY');
    $scoreBefore = CrbIdentity::where('identity_hash', $hash)->value('credit_score');

    // DPD=0, isEarly=true
    $crb->recordPayment($hash, 'nrc', 'tenant-1', 'LN-EARLY', 0, true, false, 500.0);

    $scoreAfter = CrbIdentity::where('identity_hash', $hash)->value('credit_score');
    // early_repayment = +15
    expect($scoreAfter)->toBe($scoreBefore + 15);
});

test('late payment reduces credit score proportionally to DPD', function () {
    $crb  = crbService();
    $hash = $crb->hash('300002/01/1', 'nrc');

    $crb->recordLoanOpened($hash, 'nrc', 'tenant-1', 2000.0, 'LN-LATE');
    $identity = CrbIdentity::where('identity_hash', $hash)->first();
    $scoreBefore = $identity->credit_score;

    // DPD=45, isEarly=false → late_payment_mild (-10)
    $crb->recordPayment($hash, 'nrc', 'tenant-1', 'LN-LATE', 45, false, false, 500.0);
    $scoreAfter = CrbIdentity::where('identity_hash', $hash)->value('credit_score');
    expect($scoreAfter)->toBe($scoreBefore - 10);
});

test('completed loan decrements active count and adds loan_completed points', function () {
    $crb  = crbService();
    $hash = $crb->hash('300003/01/1', 'nrc');

    $crb->recordLoanOpened($hash, 'nrc', 'tenant-1', 2000.0, 'LN-COMP');
    $crb->recordPayment($hash, 'nrc', 'tenant-1', 'LN-COMP', 0, true, true, 2000.0);

    $identity = CrbIdentity::where('identity_hash', $hash)->first();
    expect($identity->active_loan_count)->toBe(0)
        ->and($identity->total_loans_completed)->toBe(1);

    // loan_opened(-5) + early_repayment(+15) + loan_completed(+20) = 600 + 30 = 630
    expect($identity->credit_score)->toBe(630);
});

test('severe default reduces score significantly', function () {
    $crb  = crbService();
    $hash = $crb->hash('300004/01/1', 'nrc');

    $crb->recordLoanOpened($hash, 'nrc', 'tenant-1', 5000.0, 'LN-DEF');
    $identity = CrbIdentity::where('identity_hash', $hash)->first();
    $scoreBefore = $identity->credit_score;

    // DPD > 180 → 'default' = -80
    $crb->recordPayment($hash, 'nrc', 'tenant-1', 'LN-DEF', 200, false, false, 500.0);
    $scoreAfter = CrbIdentity::where('identity_hash', $hash)->value('credit_score');
    expect($scoreAfter)->toBe($scoreBefore - 80);
});

test('writeoff reduces active_loan_count and applies writeoff penalty', function () {
    $crb  = crbService();
    $hash = $crb->hash('300005/01/1', 'nrc');

    $crb->recordLoanOpened($hash, 'nrc', 'tenant-1', 5000.0, 'LN-WO');
    $crb->recordWriteoff($hash, 'nrc', 'tenant-1', 'LN-WO');

    $identity = CrbIdentity::where('identity_hash', $hash)->first();
    expect($identity->active_loan_count)->toBe(0)
        ->and($identity->total_loans_written_off)->toBe(1)
        ->and($identity->total_loans_defaulted)->toBe(1);

    // loan_opened(-5) + writeoff(-150) = 600 - 155 = 445 → clamped min 300
    expect($identity->credit_score)->toBe(445);
});

test('score is clamped at 300 minimum', function () {
    $crb  = crbService();
    $hash = $crb->hash('CLAMP001', 'tpin');

    // Pre-set score near minimum
    CrbIdentity::create([
        'identity_hash' => $hash,
        'identity_type' => 'tpin',
        'credit_score'  => 320,
        'score_band'    => 'very_poor',
    ]);

    // writeoff(-150) should not go below 300
    $crb->recordWriteoff($hash, 'tpin', 'tenant-1', 'LN-CLAMP');

    $score = CrbIdentity::where('identity_hash', $hash)->value('credit_score');
    expect($score)->toBe(300);
});

test('score is clamped at 850 maximum', function () {
    $crb  = crbService();
    $hash = $crb->hash('CLAMP002', 'tpin');

    CrbIdentity::create([
        'identity_hash'    => $hash,
        'identity_type'    => 'tpin',
        'credit_score'     => 845,
        'score_band'       => 'excellent',
        'active_loan_count'=> 1,
        'total_loans_taken'=> 1,
    ]);

    // early_repayment(+15) → 845 + 15 = 860 → clamped to 850
    $crb->recordPayment($hash, 'tpin', 'tenant-1', 'LN-MAX', 0, true, false, 1000.0);

    $score = CrbIdentity::where('identity_hash', $hash)->value('credit_score');
    expect($score)->toBe(850);
});

test('recalculateFromEvents replays all events', function () {
    $crb  = crbService();
    $hash = $crb->hash('RECALC001', 'nrc');

    $crb->recordLoanOpened($hash, 'nrc', 'tenant-1', 2000.0, 'LN-R1');
    $crb->recordPayment($hash, 'nrc', 'tenant-1', 'LN-R1', 0, true, true, 2000.0);

    $identity = CrbIdentity::where('identity_hash', $hash)->first();

    // Corrupt the score manually
    $identity->update(['credit_score' => 999]);

    // Recalculate should restore the correct value
    $crb->recalculateFromEvents($identity);

    $identity->refresh();
    // loan_opened(-5) + early_repayment(+15) + loan_completed(+20) = 600 + 30 = 630
    expect($identity->credit_score)->toBe(630);
});

test('score events are recorded with correct before/after values', function () {
    $crb  = crbService();
    $hash = $crb->hash('EVENTS001', 'nrc');

    $crb->recordLoanOpened($hash, 'nrc', 'tenant-1', 2000.0, 'LN-EV1');

    $event = CrbScoreEvent::where('identity_hash', $hash)
        ->where('event_type', 'loan_opened')
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->score_before)->toBe(600)
        ->and($event->points_change)->toBe(-5)
        ->and($event->score_after)->toBe(595);
});

// ─── API endpoint tests ───────────────────────────────────────────────────────

test('POST crb/check returns profile for known identifier', function () {
    $admin = crbAdmin();
    $crb   = crbService();
    $crb->recordLoanOpened(
        $crb->hash('500001/01/1', 'nrc'), 'nrc', 'tenant-x', 3000.0, 'LN-API1'
    );

    $response = $this->actingAs($admin)
        ->postJson(route('api.v1.crb.check'), [
            'type'  => 'nrc',
            'value' => '500001/01/1',
        ])
        ->assertOk()
        ->assertJsonPath('data.found', true)
        ->assertJsonPath('data.has_active_loans', true);

    expect($response->json('data.active_loan_count'))->toBe(1);
});

test('POST crb/check returns defaults for unknown identifier', function () {
    $admin = crbAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.crb.check'), [
            'type'  => 'nrc',
            'value' => '999999/99/1',
        ])
        ->assertOk()
        ->assertJsonPath('data.found', false)
        ->assertJsonPath('data.credit_score', 600)
        ->assertJsonPath('data.has_active_loans', false);
});

test('POST crb/check validates type field', function () {
    $admin = crbAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.crb.check'), [
            'type'  => 'passport',  // invalid
            'value' => '12345',
        ])
        ->assertStatus(422);
});

test('GET borrowers/{borrower}/crb returns borrower CRB report', function () {
    $admin    = crbAdmin();
    $borrower = crbBorrower(['national_id' => '600001/01/1']);

    $crb = crbService();
    $crb->recordLoanOpened(
        $crb->hash('600001/01/1', 'nrc'), 'nrc', 'tenant-1', 4000.0, 'LN-BRW1'
    );

    $this->actingAs($admin)
        ->getJson(route('api.v1.crb.borrower-report', $borrower))
        ->assertOk()
        ->assertJsonPath('data.primary.found', true)
        ->assertJsonPath('data.borrower_id', $borrower->id);
});

test('GET borrowers/{borrower}/crb returns 422 when borrower has no CRB identifier', function () {
    $admin    = crbAdmin();
    $borrower = Borrower::factory()->create([
        'national_id'       => null,
        'tpin_number'       => null,
        'company_reg_number'=> null,
        'is_active'         => true,
    ]);

    $this->actingAs($admin)
        ->getJson(route('api.v1.crb.borrower-report', $borrower))
        ->assertStatus(422);
});

test('POST borrowers/{borrower}/crb/recalculate recalculates the score', function () {
    $admin    = crbAdmin();
    $borrower = crbBorrower(['national_id' => '700001/01/1']);

    $crb  = crbService();
    $hash = $crb->hash('700001/01/1', 'nrc');
    $crb->recordLoanOpened($hash, 'nrc', 'tenant-1', 2000.0, 'LN-REC1');

    // Corrupt the score
    CrbIdentity::where('identity_hash', $hash)->update(['credit_score' => 999]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.crb.recalculate', $borrower))
        ->assertOk()
        ->assertJsonPath('data.credit_score', 595); // 600 - 5 (loan_opened)
});

test('GET crb/report/{hash} returns full report', function () {
    $admin = crbAdmin();
    $crb   = crbService();
    $hash  = $crb->hash('800001/01/1', 'nrc');

    $crb->recordLoanOpened($hash, 'nrc', 'tenant-1', 5000.0, 'LN-RPT1');
    $crb->recordPayment($hash, 'nrc', 'tenant-1', 'LN-RPT1', 0, true, true, 5000.0);

    $this->actingAs($admin)
        ->getJson(route('api.v1.crb.report', $hash))
        ->assertOk()
        ->assertJsonPath('data.identity_type', 'nrc')
        ->assertJsonPath('data.total_loans_taken', 1)
        ->assertJsonPath('data.total_loans_completed', 1)
        ->assertJsonPath('data.active_loan_count', 0);
});

test('GET crb/report/{hash} returns success null for unknown hash', function () {
    $admin = crbAdmin();

    $this->actingAs($admin)
        ->getJson(route('api.v1.crb.report', str_repeat('0', 64)))
        ->assertOk()
        ->assertJsonPath('data', null);
});

// ─── Cross-tenant active loan detection ──────────────────────────────────────

test('check shows has_active_loans true when another tenant opened a loan', function () {
    $crb  = crbService();
    $hash = $crb->hash('CROSS001/01/1', 'nrc');

    // Simulate a loan opened at tenant-A
    $crb->recordLoanOpened($hash, 'nrc', 'tenant-A', 5000.0, 'LN-CROSS-001');

    // Now tenant-B checks the same hash
    $result = $crb->check($hash, 'nrc', 'tenant-B', 'loan_disbursement');

    expect($result['has_active_loans'])->toBeTrue()
        ->and($result['active_loan_count'])->toBe(1)
        ->and($result['found'])->toBeTrue();
});

test('fullReport completion_rate is correct', function () {
    $crb  = crbService();
    $hash = $crb->hash('COMP-RATE-001', 'tpin');

    $crb->recordLoanOpened($hash, 'tpin', 'tenant-1', 1000.0, 'LN-C1');
    $crb->recordPayment($hash, 'tpin', 'tenant-1', 'LN-C1', 0, true, true, 1000.0);

    $crb->recordLoanOpened($hash, 'tpin', 'tenant-1', 1000.0, 'LN-C2');
    // LN-C2 not completed

    $report = $crb->fullReport($hash);

    expect($report['total_loans_taken'])->toBe(2)
        ->and($report['total_loans_completed'])->toBe(1)
        ->and($report['completion_rate'])->toBe(50.0);
});
