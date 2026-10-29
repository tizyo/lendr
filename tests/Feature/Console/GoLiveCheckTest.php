<?php

use App\Console\Commands\GoLiveCheckCommand;
use App\Models\Landlord\LandlordUser;
use App\Models\Landlord\PlatformEmailConfig;
use Illuminate\Support\Facades\Cache;

function productionLikeConfig(): void
{
    config([
        'app.debug' => false,
        'app.url' => 'https://lendr.test',
        'mail.default' => 'platform',
        'queue.default' => 'database',
    ]);
    Cache::forever(GoLiveCheckCommand::HEARTBEAT_KEY, now());
    PlatformEmailConfig::create(['driver' => 'resend', 'api_key' => 're_live_x', 'is_active' => true]);
    LandlordUser::factory()->create(['is_active' => true]);
}

it('passes the platform checks on a healthy setup', function () {
    productionLikeConfig();

    $this->artisan('lendr:go-live-check')
        ->expectsOutputToContain('Sending via Resend')
        ->expectsOutputToContain('No failures')
        ->assertSuccessful();
});

it('fails when debug mode is on, cron is silent and no email provider is set', function () {
    config(['app.debug' => true, 'app.url' => 'https://lendr.test', 'mail.default' => 'platform']);
    Cache::forget(GoLiveCheckCommand::HEARTBEAT_KEY);

    $this->artisan('lendr:go-live-check')
        ->expectsOutputToContain('APP_DEBUG=true')
        ->expectsOutputToContain('No heartbeat')
        ->expectsOutputToContain('No provider saved')
        ->assertFailed();
});

it('treats a stale scheduler heartbeat as cron not running', function () {
    productionLikeConfig();
    Cache::forever(GoLiveCheckCommand::HEARTBEAT_KEY, now()->subMinutes(20));

    $this->artisan('lendr:go-live-check')
        ->expectsOutputToContain('Last tick 20 min ago')
        ->assertFailed();
});

it('schedules the heartbeat every minute', function () {
    $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(fn ($e) => $e->description === 'scheduler-heartbeat');

    expect($event)->not->toBeNull()->and($event->expression)->toBe('* * * * *');
});
