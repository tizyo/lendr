<?php

use App\Commands\BackupTenantDatabasesCommand;
use App\Commands\ProcessOverdueLoansCommand;
use App\Commands\ProcessTrialExpiryCommand;
use App\Commands\ProcessUpcomingPaymentRemindersCommand;
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
| These run within each tenant's context via a tenancy-aware wrapper.
| Ensure the queue worker and scheduler cron (php artisan schedule:run)
| are both active on the server.
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
