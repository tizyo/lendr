<?php

use App\Commands\AccrueInterestCommand;
use App\Commands\AccrueSavingsInterestCommand;
use App\Commands\BackupTenantDatabasesCommand;
use App\Commands\CheckPendingPayoutsCommand;
use App\Commands\EscalateCollectionsCommand;
use App\Commands\ExpireKycDocumentsCommand;
use App\Commands\ProcessCampaignsCommand;
use App\Commands\ProcessComplianceRemindersCommand;
use App\Commands\ProcessCrmFollowupsCommand;
use App\Commands\ProcessOverdueLoansCommand;
use App\Commands\ProcessStandingOrdersCommand;
use App\Commands\ProcessTrialExpiryCommand;
use App\Commands\ProcessUpcomingPaymentRemindersCommand;
use App\Commands\RunReconciliationCommand;
use App\Commands\SendBorrowerStatementsCommand;
use App\Console\Commands\ExpireFeaturedItemsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| LENDR — Scheduled Tasks
| Tenant-level commands use App\Commands\Concerns\RunsForEachTenant, which
| runs them inside every active / trial tenant (the scheduler itself runs on
| the central connection). Ensure the queue worker and scheduler cron
| (php artisan schedule:run) are both active on the server.
|
| Not scheduled on purpose: lendr:apply-penalties — penalties are already
| accrued by lendr:process-overdue; running both would charge borrowers twice.
|--------------------------------------------------------------------------
*/

// 01:00 daily — accrue penalties on overdue installments, auto-default severely delinquent loans
Schedule::command(ProcessOverdueLoansCommand::class)
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/overdue-processor.log'));

// 08:00 daily — SMS reminders for payments due in 1, 3, or 7 days
Schedule::command(ProcessUpcomingPaymentRemindersCommand::class)
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/payment-reminders.log'));

// 1st of each month 06:00 — email monthly loan account statements to borrowers
Schedule::command(SendBorrowerStatementsCommand::class)
    ->monthlyOn(1, '06:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/borrower-statements.log'));

// 07:00 daily — trial expiry warnings (3d & 1d) and status=expired for lapsed trials
Schedule::command(ProcessTrialExpiryCommand::class)
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/trial-expiry.log'));

// Hourly — deactivate expired featured repo item slots and hot deals
Schedule::command(ExpireFeaturedItemsCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/featured-expiry.log'));

// 02:00 daily — dump the landlord database and upload to the backup disk
Schedule::command('backup:run --only-db')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/backup.log'));

// 02:30 daily — dump every tenant database and upload to the backup disk
Schedule::command(BackupTenantDatabasesCommand::class)
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/backup-tenants.log'));

// 03:00 daily — prune old backups per the retention policy in config/backup.php
Schedule::command('backup:clean')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/backup-clean.log'));

// 04:00 daily — auto-reconcile bank statements imported but not yet matched
Schedule::command(RunReconciliationCommand::class)
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/reconciliation.log'));

// 00:30 daily — record yesterday's interest accrual per active loan (idempotent per date)
Schedule::command(AccrueInterestCommand::class)
    ->dailyAt('00:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/interest-accrual.log'));

// 00:45 daily — mark KYC documents past their expiry date as expired
Schedule::command(ExpireKycDocumentsCommand::class)
    ->dailyAt('00:45')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/kyc-expiry.log'));

// 06:00 daily — dispatch auto-debit collections for due standing orders (Enterprise wallets)
Schedule::command(ProcessStandingOrdersCommand::class)
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/standing-orders.log'));

// 07:30 daily — flag overdue / upcoming regulatory compliance events
Schedule::command(ProcessComplianceRemindersCommand::class)
    ->dailyAt('07:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/compliance-reminders.log'));

// 08:00 daily — remind staff of CRM leads due for follow-up
Schedule::command(ProcessCrmFollowupsCommand::class)
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/crm-followups.log'));

// 09:00 daily — escalate collection cases by DPD rules, evaluate promises-to-pay
Schedule::command(EscalateCollectionsCommand::class)
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/collections-escalation.log'));

// Every 30 minutes — ask providers what happened to accepted mobile-money payouts
// (completed / failed after acceptance → reversed / stuck → staff verify)
Schedule::command(CheckPendingPayoutsCommand::class)
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/payout-checks.log'));

// Every 5 minutes — send campaigns whose scheduled time has arrived
Schedule::command(ProcessCampaignsCommand::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/campaigns.log'));

// 1st of each month 00:15 — monthly savings interest (once per account per month)
Schedule::command(AccrueSavingsInterestCommand::class)
    ->monthlyOn(1, '00:15')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/savings-interest.log'));
