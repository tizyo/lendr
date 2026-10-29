<?php

namespace App\Console\Commands;

use App\Models\Landlord\LandlordUser;
use App\Models\Landlord\PlatformEmailConfig;
use App\Models\Landlord\PlatformSmsConfig;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\TenantWallet;
use App\Models\Tenant\DisbursementLog;
use App\Models\Tenant\PaymentProof;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Production readiness check (the server-side half of the go-live checklist).
 * Changes no data — the only write is a tiny probe file per storage disk,
 * deleted straight away. Exits non-zero when anything FAILs.
 *
 *   php artisan lendr:go-live-check
 *   php artisan lendr:go-live-check --tenant=vozara
 */
class GoLiveCheckCommand extends Command
{
    protected $signature = 'lendr:go-live-check {--tenant= : Only check this workspace (slug)}';

    protected $description = 'Check that production is configured and healthy enough for real clients (read-only).';

    public const HEARTBEAT_KEY = 'lendr:scheduler-heartbeat';

    /** @var array<int, array{0: string, 1: string, 2: string, 3: string}> [scope, status, check, detail] */
    private array $rows = [];

    public function handle(): int
    {
        $this->platformChecks();

        $tenants = Tenant::query()
            ->whereIn('status', ['active', 'trial'])
            ->when($this->option('tenant'), fn ($q, $slug) => $q->where('slug', $slug))
            ->orderBy('created_at')
            ->get();

        if ($tenants->isEmpty()) {
            $this->add('platform', 'warn', 'Live workspaces', $this->option('tenant') ? 'No active workspace with that slug.' : 'No active or trial workspaces yet.');
        }

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(fn () => $this->tenantChecks($tenant));
            } catch (\Throwable $e) {
                $this->add($tenant->slug, 'fail', 'Workspace reachable', Str::limit($e->getMessage(), 120));
            }
        }

        $this->table(['Where', 'Status', 'Check', 'Detail'], array_map(fn ($r) => [
            $r[0], match ($r[1]) {
                'pass' => '<info>PASS</info>', 'warn' => '<comment>WARN</comment>', default => '<error>FAIL</error>'
            }, $r[2], $r[3],
        ], $this->rows));

        $fails = count(array_filter($this->rows, fn ($r) => $r[1] === 'fail'));
        $warns = count(array_filter($this->rows, fn ($r) => $r[1] === 'warn'));

        $fails
            ? $this->error("{$fails} check(s) failed, {$warns} warning(s). Fix the failures before onboarding a client.")
            : $this->info("No failures ({$warns} warning(s)). Review warnings against the go-live checklist.");

        return $fails ? self::FAILURE : self::SUCCESS;
    }

    // ─── Platform ─────────────────────────────────────────────────────────────

    private function platformChecks(): void
    {
        $p = 'platform';

        $this->add($p, app()->environment('production') ? 'pass' : 'warn', 'Environment', 'APP_ENV='.app()->environment());
        $this->add($p, config('app.debug') ? 'fail' : 'pass', 'Debug mode off', config('app.debug') ? 'APP_DEBUG=true exposes stack traces and secrets.' : 'APP_DEBUG=false');
        $this->add($p, filled(config('app.key')) ? 'pass' : 'fail', 'APP_KEY set', 'Back it up — encrypted NRCs are unreadable without it.');

        $url = (string) config('app.url');
        $this->add($p, str_starts_with($url, 'https://') ? 'pass' : 'fail', 'HTTPS app URL', "APP_URL={$url} (service worker, push and email links need https)");

        $this->add($p, ...$this->pendingMigrations(null));

        $heartbeat = Cache::get(self::HEARTBEAT_KEY);
        $age = $heartbeat ? (int) floor(now()->diffInMinutes($heartbeat, true)) : null; // Carbon 3 returns a float
        $this->add($p, $age !== null && $age <= 5 ? 'pass' : 'fail', 'Scheduler running (cron)',
            $age === null ? 'No heartbeat — add the cron: * * * * * php artisan schedule:run' : "Last tick {$age} min ago");

        $this->add($p, ...$this->queueHealth());

        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count() : 0;
        $this->add($p, $failed ? 'warn' : 'pass', 'Failed jobs (24h)', $failed ? "{$failed} failed — php artisan queue:failed" : 'None');

        $mail = rescue(fn () => PlatformEmailConfig::active(), null, false);
        $this->add($p, config('mail.default') === 'platform' && $mail?->isConfigured() ? 'pass' : 'fail', 'Email delivery',
            config('mail.default') !== 'platform'
                ? 'MAIL_MAILER should be "platform".'
                : ($mail?->isConfigured() ? 'Sending via '.($mail->usesResend() ? 'Resend' : 'SMTP').' — confirm with Send Test.' : 'No provider saved in Landlord → Platform Settings → Email.'));

        $sms = rescue(fn () => PlatformSmsConfig::where('is_active', true)->first(), null, false);
        $this->add($p, $sms ? 'pass' : 'warn', 'SMS provider', $sms ? "Active: {$sms->provider}" : 'None active — OTP logins and borrower SMS will not send.');

        $this->add($p, filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key')) ? 'pass' : 'warn',
            'Push notifications (VAPID)', 'php artisan lendr:webpush-keys, then add the keys to .env');

        foreach (['public', 'private'] as $disk) {
            $this->add($p, ...$this->diskWritable($disk));
        }

        $landlords = rescue(fn () => LandlordUser::where('is_active', true)->count(), 0, false);
        $with2fa = rescue(fn () => LandlordUser::where('is_active', true)->whereNotNull('two_factor_confirmed_at')->count(), 0, false);
        $this->add($p, $landlords ? ($with2fa === $landlords ? 'pass' : 'warn') : 'fail', 'Landlord accounts',
            $landlords ? "{$landlords} active, {$with2fa} with 2FA" : 'None — nobody can manage the platform.');

        $this->add($p, config('security.csp.enforce') ? 'pass' : 'warn', 'Content-Security-Policy',
            config('security.csp.enforce') ? 'Enforced' : 'Report-only — enforce once the browser console is clean.');
    }

    // ─── Per workspace ────────────────────────────────────────────────────────

    private function tenantChecks(Tenant $tenant): void
    {
        $t = $tenant->slug;

        $this->add($t, ...$this->pendingMigrations(base_path('database/migrations/tenant')));
        $this->add($t, ...$this->diskWritable('private'));

        $settings = DB::table('settings')->pluck('value', 'key');

        $hasMomo = collect(['pawapay_api_key', 'airtel_client_id', 'mtn_api_user', 'zamtel_api_key'])->contains(fn ($k) => filled($settings->get($k)))
            || rescue(fn () => (bool) TenantWallet::forTenant($tenant->id)?->is_active, false, false);
        $this->add($t, $hasMomo ? 'pass' : 'warn', 'Mobile money provider', $hasMomo ? 'Configured' : 'None — disbursements and repayments are cash / bank only.');

        $bank = filled($settings->get('bank_name')) && filled($settings->get('bank_account_number'));
        $this->add($t, $bank ? 'pass' : 'warn', 'Bank account for repayments', $bank ? 'Shown to borrowers' : 'Not set — the "Bank deposit" option is hidden.');

        $threshold = (float) $settings->get('fund_low_balance_threshold', 0);
        $this->add($t, $threshold > 0 ? 'pass' : 'warn', 'Low fund balance alert', $threshold > 0 ? 'ZMW '.number_format($threshold, 2) : 'Not set — nobody is told when the fund runs low.');

        $unconfirmed = DisbursementLog::where('status', 'unconfirmed')->count();
        $this->add($t, $unconfirmed ? 'fail' : 'pass', 'Unconfirmed payouts', $unconfirmed ? "{$unconfirmed} need checking with the provider" : 'None');

        $staleProofs = Schema::hasTable('payment_proofs') ? PaymentProof::pending()->where('created_at', '<=', now()->subDays(2))->count() : 0;
        $this->add($t, $staleProofs ? 'warn' : 'pass', 'Bank deposits awaiting review', $staleProofs ? "{$staleProofs} waiting over 2 days" : 'None overdue');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** @return array{0: string, 1: string, 2: string} */
    private function pendingMigrations(?string $path): array
    {
        $migrator = app('migrator');

        if (! $migrator->repositoryExists()) {
            return ['fail', 'Migrations', 'Migrations table missing — run migrate.'];
        }

        $files = array_keys($migrator->getMigrationFiles($path ? [$path] : array_merge([database_path('migrations')], $migrator->paths())));
        $pending = array_diff($files, $migrator->getRepository()->getRan());

        return $pending
            ? ['fail', 'Migrations', count($pending).' pending — run '.($path ? 'php artisan tenants:migrate --force' : 'php artisan migrate --force')]
            : ['pass', 'Migrations', 'Up to date'];
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function queueHealth(): array
    {
        $connection = config('queue.default');

        if ($connection === 'sync') {
            return ['warn', 'Queue workers', 'QUEUE_CONNECTION=sync — jobs run inside web requests; use database or redis.'];
        }

        if ($connection === 'database') {
            $table = config('queue.connections.database.table', 'jobs');
            $stuck = DB::table($table)->whereNull('reserved_at')->where('available_at', '<', now()->subMinutes(5)->getTimestamp())->count();

            return $stuck
                ? ['fail', 'Queue workers', "{$stuck} job(s) waiting over 5 min — is a worker running?"]
                : ['pass', 'Queue workers', 'No backlog'];
        }

        return ['warn', 'Queue workers', "Using {$connection}; check the worker in Horizon / your process manager."];
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function diskWritable(string $disk): array
    {
        $probe = '.go-live-check-'.Str::random(8);

        try {
            $ok = Storage::disk($disk)->put($probe, 'ok') && Storage::disk($disk)->get($probe) === 'ok';
            Storage::disk($disk)->delete($probe);
        } catch (\Throwable) {
            $ok = false;
        }

        return [$ok ? 'pass' : 'fail', "Storage: {$disk}", $ok ? 'Writable' : 'Not writable — uploads will fail.'];
    }

    private function add(string $scope, string $status, string $check, string $detail = ''): void
    {
        $this->rows[] = [$scope, $status, $check, $detail];
    }
}
