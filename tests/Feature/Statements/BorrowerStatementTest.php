<?php

use App\Commands\SendBorrowerStatementsCommand;
use App\Enums\LoanStatus;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Services\Mail\TenantMailService;
use Illuminate\Support\Facades\Log;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function statementLoan(Borrower $borrower): Loan
{
    $type = LoanType::first() ?? LoanType::factory()->create();
    $plan = LoanPlan::first() ?? LoanPlan::factory()->create(['loan_type_id' => $type->id]);

    return Loan::factory()->create([
        'borrower_id'  => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'status'       => LoanStatus::Active,
    ]);
}

// ─── Tests ───────────────────────────────────────────────────────────────────

test('command sends statement email to borrowers with active loans and email', function () {
    $mailer = Mockery::mock(TenantMailService::class);
    $mailer->shouldReceive('raw')->once();
    $this->app->instance(TenantMailService::class, $mailer);

    $borrower = Borrower::factory()->create(['email' => 'jane@example.com']);
    statementLoan($borrower);

    $this->artisan('lendr:send-statements')
        ->assertExitCode(0);
});

test('command skips borrowers without email', function () {
    $mailer = Mockery::mock(TenantMailService::class);
    $mailer->shouldNotReceive('raw');
    $this->app->instance(TenantMailService::class, $mailer);

    $borrower = Borrower::factory()->create(['email' => null]);
    statementLoan($borrower);

    $this->artisan('lendr:send-statements')
        ->assertExitCode(0);
});

test('command skips borrowers with no active loans', function () {
    $mailer = Mockery::mock(TenantMailService::class);
    $mailer->shouldNotReceive('raw');
    $this->app->instance(TenantMailService::class, $mailer);

    // Borrower has email but completed loan — not active
    $type = LoanType::first() ?? LoanType::factory()->create();
    $plan = LoanPlan::first() ?? LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create(['email' => 'completed@example.com']);
    Loan::factory()->create([
        'borrower_id'  => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'status'       => LoanStatus::Completed,
    ]);

    $this->artisan('lendr:send-statements')
        ->assertExitCode(0);
});

test('dry-run does not send any emails', function () {
    $mailer = Mockery::mock(TenantMailService::class);
    $mailer->shouldNotReceive('raw');
    $this->app->instance(TenantMailService::class, $mailer);

    $borrower = Borrower::factory()->create(['email' => 'dry@example.com']);
    statementLoan($borrower);

    $this->artisan('lendr:send-statements --dry-run')
        ->assertExitCode(0);
});

test('command accepts custom month and year options', function () {
    $mailer = Mockery::mock(TenantMailService::class);
    $mailer->shouldReceive('raw')->once()->withArgs(function ($to, $subject) {
        return str_contains($subject, 'February 2026');
    });
    $this->app->instance(TenantMailService::class, $mailer);

    $borrower = Borrower::factory()->create(['email' => 'custom@example.com']);
    statementLoan($borrower);

    $this->artisan('lendr:send-statements --month=2 --year=2026')
        ->assertExitCode(0);
});

test('command sends to multiple borrowers', function () {
    $mailer = Mockery::mock(TenantMailService::class);
    $mailer->shouldReceive('raw')->times(3);
    $this->app->instance(TenantMailService::class, $mailer);

    foreach (['a@ex.com', 'b@ex.com', 'c@ex.com'] as $email) {
        $borrower = Borrower::factory()->create(['email' => $email]);
        statementLoan($borrower);
    }

    $this->artisan('lendr:send-statements')
        ->assertExitCode(0);
});

test('command continues when one email fails', function () {
    $mailer = Mockery::mock(TenantMailService::class);
    $mailer->shouldReceive('raw')
        ->times(2)
        ->andReturnUsing(function () {
            static $calls = 0;
            $calls++;
            if ($calls === 1) {
                throw new \RuntimeException('SMTP timeout');
            }
        });
    $this->app->instance(TenantMailService::class, $mailer);

    foreach (['fail@ex.com', 'ok@ex.com'] as $email) {
        $borrower = Borrower::factory()->create(['email' => $email]);
        statementLoan($borrower);
    }

    // Should not throw — continues past failure
    $this->artisan('lendr:send-statements')
        ->assertExitCode(0);
});
