<?php

use App\Enums\FundTransactionType;
use App\Enums\UserRole;
use App\Jobs\SendLowFundBalanceAlertJob;
use App\Mail\LowFundBalanceMail;
use App\Models\Tenant\FundBalance;
use App\Models\Tenant\InAppNotification;
use App\Models\Tenant\Loan;
use App\Models\Tenant\User;
use App\Services\FundService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

function fundWith(float $available): void
{
    FundBalance::current()->update(['available_balance' => $available]);
}

function alertAt(float $threshold): void
{
    DB::table('settings')->updateOrInsert(['key' => 'fund_low_balance_threshold'], ['value' => $threshold, 'updated_at' => now()]);
}

function debitFund(float $amount): void
{
    app(FundService::class)->debit($amount, FundTransactionType::Disburse, Loan::factory()->create(), null);
}

it('alerts when a debit takes the fund below the threshold', function () {
    Queue::fake();
    alertAt(10000);
    fundWith(12000);

    debitFund(5000);

    Queue::assertPushed(SendLowFundBalanceAlertJob::class, fn ($job) => $job->balance === 7000.0 && $job->threshold === 10000.0);
});

it('alerts only on the crossing, not on every debit while already low', function () {
    Queue::fake();
    alertAt(10000);
    fundWith(8000);

    debitFund(1000);

    Queue::assertNotPushed(SendLowFundBalanceAlertJob::class);
});

it('does nothing when no threshold is set', function () {
    Queue::fake();
    fundWith(12000);

    debitFund(11000);

    Queue::assertNotPushed(SendLowFundBalanceAlertJob::class);
});

it('notifies managers and accountants in-app and by email', function () {
    Mail::fake();
    $manager = User::factory()->create(['role' => UserRole::BranchManager, 'is_active' => true]);
    $accountant = User::factory()->create(['role' => UserRole::Accountant, 'is_active' => true]);
    $officer = User::factory()->create(['role' => UserRole::LoanOfficer, 'is_active' => true]);

    dispatch_sync(new SendLowFundBalanceAlertJob(7000, 10000));

    expect(InAppNotification::where('type', 'fund_low_balance')->pluck('user_id')->all())
        ->toContain($manager->id, $accountant->id)
        ->not->toContain($officer->id);

    Mail::assertSent(LowFundBalanceMail::class, fn ($mail) => $mail->hasTo($manager->email));
    Mail::assertNotSent(LowFundBalanceMail::class, fn ($mail) => $mail->hasTo($officer->email));
});

it('renders the alert email with the balance and shortfall', function () {
    $html = (new LowFundBalanceMail(User::factory()->create(['name' => 'Mwila Banda']), 7000, 10000))->render();

    expect($html)->toContain('ZMW 7,000.00')->toContain('ZMW 3,000.00')->toContain('Hi Mwila');
});
