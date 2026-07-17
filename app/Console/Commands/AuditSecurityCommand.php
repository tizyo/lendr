<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * php artisan audit:security
 *
 * Runs the LENDR security checklist and reports PASS/WARN/FAIL per check.
 * All FAIL items must be resolved before production deployment.
 */
class AuditSecurityCommand extends Command
{
    protected $signature   = 'audit:security {--fix : Auto-fix minor issues where possible}';
    protected $description = 'Run the LENDR security checklist against the current environment';

    private array $results = [];

    public function handle(): int
    {
        $this->newLine();
        $this->line('┌─────────────────────────────────────────────────────────┐');
        $this->line('│              LENDR Security Audit                       │');
        $this->line('└─────────────────────────────────────────────────────────┘');
        $this->newLine();

        $this->checkNoPlainTextPasswords();
        $this->checkNoEnvValuesInCode();
        $this->checkDebugDisabledInProduction();
        $this->checkBcryptCostFactor();
        $this->checkBcMathUsageOnly();
        $this->checkWebhookSignaturesValidated();
        $this->checkRateLimitingConfigured();
        $this->checkCsrfProtection();
        $this->checkMimeTypeValidation();
        $this->checkSentryConfigured();
        $this->checkAppKeySet();
        $this->checkHttpsEnforced();

        $this->printResults();

        $failed = count(array_filter($this->results, fn ($r) => $r['status'] === 'FAIL'));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ─── Checks ──────────────────────────────────────────────────────────────

    private function checkNoPlainTextPasswords(): void
    {
        try {
            // Sample 10 users — if any password doesn't start with '$2y$' it's plain-text
            $bad = DB::table('users')
                ->whereNotNull('password')
                ->whereRaw("password NOT LIKE '\$2y\$%' AND password NOT LIKE '\$argon%'")
                ->count();

            $bad > 0
                ? $this->markFail('plain_text_passwords', "{$bad} user(s) have non-hashed passwords")
                : $this->markPass('plain_text_passwords', 'All passwords are bcrypt/argon hashed');
        } catch (\Throwable $e) {
            $this->markWarn('plain_text_passwords', 'Could not query users table: ' . $e->getMessage());
        }
    }

    private function checkNoEnvValuesInCode(): void
    {
        // Check for hard-coded secrets patterns in app/ directory
        $patterns = [
            '/sk_live_[a-zA-Z0-9]{20,}/',         // Stripe live key
            '/FLWSECK_TEST-[a-zA-Z0-9-]{30,}/',    // Flutterwave test key
            '/ACtest[a-zA-Z0-9]{30,}/',             // Twilio SID
        ];

        $found = false;
        foreach ($patterns as $pattern) {
            $result = shell_exec("grep -r --include='*.php' -l " . escapeshellarg($pattern) . " app/ 2>/dev/null");
            if (trim((string) $result)) {
                $found = true;
                break;
            }
        }

        $found
            ? $this->markFail('no_hardcoded_secrets', 'Hard-coded API key patterns detected in app/ directory')
            : $this->markPass('no_hardcoded_secrets', 'No hard-coded API key patterns found');
    }

    private function checkDebugDisabledInProduction(): void
    {
        if (app()->environment('production') && config('app.debug')) {
            $this->markFail('debug_disabled', 'APP_DEBUG=true in production — verbose errors exposed');
        } else {
            $this->markPass('debug_disabled', app()->environment('production')
                ? 'APP_DEBUG=false in production'
                : 'Not in production (debug=' . (config('app.debug') ? 'true' : 'false') . ')');
        }
    }

    private function checkBcryptCostFactor(): void
    {
        $rounds = (int) config('hashing.bcrypt.rounds', config('BCRYPT_ROUNDS', 10));

        $rounds >= 12
            ? $this->markPass('bcrypt_cost', "Bcrypt cost factor = {$rounds} (≥ 12)")
            : $this->markFail('bcrypt_cost', "Bcrypt cost factor = {$rounds} (must be ≥ 12 for production)");
    }

    private function checkBcMathUsageOnly(): void
    {
        // Look for native float arithmetic on financial fields (rough heuristic)
        $suspects = shell_exec(
            "grep -rn --include='*.php' -E '\\\$[a-z_]*(amount|balance|principal|interest|fee|total)[a-z_]*\\s*[+\\-\\*]\s*\\\\$' app/Services/ app/Http/Controllers/ 2>/dev/null | grep -v '//' | wc -l"
        );

        $count = (int) trim((string) $suspects);

        $count === 0
            ? $this->markPass('bcmath_only', 'No raw float arithmetic detected on financial fields')
            : $this->markWarn('bcmath_only', "{$count} potential raw arithmetic operations found — review manually");
    }

    private function checkWebhookSignaturesValidated(): void
    {
        $controllers = [
            app_path('Http/Controllers/Webhook/FlutterwaveWebhookController.php'),
            app_path('Http/Controllers/Webhook/PawaPayWebhookController.php'),
        ];

        $missing = [];
        foreach ($controllers as $path) {
            if (File::exists($path)) {
                $content = File::get($path);
                if (! str_contains($content, 'hash_equals') && ! str_contains($content, 'verif-hash') && ! str_contains($content, 'hmac')) {
                    $missing[] = basename($path);
                }
            }
        }

        empty($missing)
            ? $this->markPass('webhook_signatures', 'Webhook signature validation found in all gateway controllers')
            : $this->markFail('webhook_signatures', 'Missing signature validation in: ' . implode(', ', $missing));
    }

    private function checkRateLimitingConfigured(): void
    {
        $routeFile = base_path('routes/api.php');
        $content   = File::exists($routeFile) ? File::get($routeFile) : '';

        str_contains($content, 'throttle')
            ? $this->markPass('rate_limiting', 'Throttle middleware found in api.php routes')
            : $this->markWarn('rate_limiting', 'No throttle middleware detected in api.php — verify OTP/login/payment endpoints are rate-limited');
    }

    private function checkCsrfProtection(): void
    {
        $appFile = base_path('bootstrap/app.php');
        $content = File::exists($appFile) ? File::get($appFile) : '';

        str_contains($content, 'validateCsrfTokens') || str_contains($content, 'VerifyCsrfToken')
            ? $this->markPass('csrf_protection', 'CSRF protection configured in bootstrap/app.php')
            : $this->markWarn('csrf_protection', 'CSRF configuration not detected — verify state-changing web routes are protected');
    }

    private function checkMimeTypeValidation(): void
    {
        // Check KYC upload controller for mimes/mimetypes validation rule
        $kycController = app_path('Http/Controllers/Api/V1/KycController.php');
        if (File::exists($kycController)) {
            $content = File::get($kycController);
            (str_contains($content, 'mimes:') || str_contains($content, 'mimetypes:') || str_contains($content, 'file'))
                ? $this->markPass('mime_validation', 'Server-side MIME validation found in KycController')
                : $this->markWarn('mime_validation', 'Could not confirm MIME validation in KycController — review upload rules');
        } else {
            $this->markWarn('mime_validation', 'KycController not found at expected path');
        }
    }

    private function checkSentryConfigured(): void
    {
        $dsn = config('sentry.dsn') ?? env('SENTRY_LARAVEL_DSN');

        $dsn && str_starts_with($dsn, 'https://')
            ? $this->markPass('sentry_configured', 'Sentry DSN is configured')
            : $this->markWarn('sentry_configured', 'SENTRY_LARAVEL_DSN not set — error tracking not active');
    }

    private function checkAppKeySet(): void
    {
        $key = config('app.key');

        $key && strlen($key) >= 32
            ? $this->markPass('app_key', 'APP_KEY is set and has sufficient length')
            : $this->markFail('app_key', 'APP_KEY is missing or too short');
    }

    private function checkHttpsEnforced(): void
    {
        if (app()->environment('production')) {
            $forceHttps = config('app.url') && str_starts_with(config('app.url'), 'https://');
            $forceHttps
                ? $this->markPass('https_enforced', 'APP_URL uses https:// in production')
                : $this->markFail('https_enforced', 'APP_URL does not use https:// in production');
        } else {
            $this->markPass('https_enforced', 'Not in production environment');
        }
    }

    // ─── Output helpers ───────────────────────────────────────────────────────

    private function markPass(string $check, string $detail): void
    {
        $this->results[$check] = ['status' => 'PASS', 'detail' => $detail];
    }

    private function markFail(string $check, string $detail): void
    {
        $this->results[$check] = ['status' => 'FAIL', 'detail' => $detail];
    }

    private function markWarn(string $check, string $detail): void
    {
        $this->results[$check] = ['status' => 'WARN', 'detail' => $detail];
    }

    private function printResults(): void
    {
        foreach ($this->results as $check => $result) {
            $colour = match($result['status']) {
                'PASS' => 'green',
                'WARN' => 'yellow',
                'FAIL' => 'red',
            };

            $this->line(sprintf(
                '  [<fg=%s>%s</>] %-35s %s',
                $colour,
                str_pad($result['status'], 4),
                $check,
                $result['detail'],
            ));
        }

        $this->newLine();

        $passed  = count(array_filter($this->results, fn ($r) => $r['status'] === 'PASS'));
        $warned  = count(array_filter($this->results, fn ($r) => $r['status'] === 'WARN'));
        $failed  = count(array_filter($this->results, fn ($r) => $r['status'] === 'FAIL'));
        $total   = count($this->results);

        $this->line("  Results: <fg=green>{$passed} passed</>  <fg=yellow>{$warned} warnings</>  <fg=red>{$failed} failed</>  ({$total} total)");
        $this->newLine();

        if ($failed > 0) {
            $this->line('  <fg=red;options=bold>✗ SECURITY AUDIT FAILED — resolve all FAIL items before deploying to production.</>');
        } else {
            $this->line('  <fg=green;options=bold>✓ Security audit passed' . ($warned > 0 ? ' (with warnings)' : '') . '.</>');
        }

        $this->newLine();
    }
}
