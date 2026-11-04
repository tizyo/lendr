<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * The workspace's key/value settings, with provider credentials encrypted at
 * rest: anyone reading the database or a backup used to get every lender's
 * mobile-money, SMS and SMTP keys in plain text.
 *
 * Secrets are stored as "enc:" + Crypt::encryptString(); values saved before this
 * (no prefix) still read as they are until the next save (or the migration that
 * encrypts them). APP_KEY must never be rotated without re-encrypting.
 */
class TenantSettings
{
    private const PREFIX = 'enc:';

    /** Credentials and webhook secrets: encrypted, and never sent back to the browser. */
    public const SECRETS = [
        'smtp_password', 'sms_api_key', 'mobile_money_secret',
        'pawapay_api_key', 'pawapay_webhook_secret',
        'airtel_client_secret', 'airtel_pin', 'airtel_webhook_secret',
        'mtn_subscription_key', 'mtn_disbursement_subscription_key', 'mtn_api_key', 'mtn_callback_api_key',
        'zamtel_api_key', 'zamtel_webhook_secret',
        'lipila_api_key', 'lipila_webhook_secret',
        'flutterwave_webhook_secret',
    ];

    /** Settings only an Enterprise workspace may set (its own mail and SMS providers). */
    public const ENTERPRISE_ONLY = [
        'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'smtp_from_email', 'smtp_from_name',
        'sms_gateway', 'sms_api_key', 'sms_sender_name', 'sms_username',
    ];

    public static function isSecret(string $key): bool
    {
        return in_array($key, self::SECRETS, true);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = DB::table('settings')->where('key', $key)->value('value');

        return $value === null ? $default : self::reveal($key, $value);
    }

    /** @param  list<string>  $keys @return Collection<string, mixed> */
    public static function many(array $keys): Collection
    {
        return DB::table('settings')->whereIn('key', $keys)->pluck('value', 'key')
            ->map(fn ($value, $key) => self::reveal($key, $value));
    }

    /** @return Collection<string, mixed> */
    public static function all(): Collection
    {
        return DB::table('settings')->pluck('value', 'key')
            ->map(fn ($value, $key) => self::reveal($key, $value));
    }

    /** The placeholder a masked secret is shown as; sending it back means "unchanged". */
    public const MASK = '••••••••';

    /** Set by their own screens or by the platform, never through the generic form. */
    private const RESERVED = ['company_logo_path', 'marketplace_enabled'];

    /**
     * Save settings from a form: validates keys and a few typed values, keeps
     * Enterprise-only providers to Enterprise workspaces, encrypts secrets and
     * ignores secrets sent back as the mask.
     *
     * @param  array<string, mixed>  $settings
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function save(array $settings): void
    {
        $errors = [];
        $enterprise = ! tenancy()->tenant || tenancy()->tenant->plan === 'enterprise';

        foreach ($settings as $key => $value) {
            $field = "settings.{$key}";

            if (! is_string($key) || ! preg_match('/^[a-z0-9_]{1,64}$/', $key) || in_array($key, self::RESERVED, true)) {
                $errors[$field] = "\"{$key}\" can't be set here.";

                continue;
            }
            if (! is_null($value) && ! is_scalar($value)) {
                $errors[$field] = 'Must be a single value.';

                continue;
            }
            if (is_string($value) && mb_strlen($value) > 2000) {
                $errors[$field] = 'Too long (2,000 characters at most).';

                continue;
            }
            // A changed value only: a workspace that dropped from Enterprise still sends its old values back.
            if (! $enterprise && in_array($key, self::ENTERPRISE_ONLY, true) && filled($value) && $value !== self::MASK
                && (string) $value !== (string) self::get($key)) {
                $errors[$field] = 'Your own email and SMS providers are part of the Enterprise plan.';

                continue;
            }
            if (in_array($key, ['primary_color', 'secondary_color', 'pwa_theme_color'], true) && filled($value) && ! preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value)) {
                $errors[$field] = 'Use a colour like #2563EB.';

                continue;
            }
            if (str_ends_with($key, '_threshold') && filled($value) && (! is_numeric($value) || $value < 0)) {
                $errors[$field] = 'Must be a number of 0 or more.';

                continue;
            }
        }

        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        foreach ($settings as $key => $value) {
            if (self::isSecret($key) && $value === self::MASK) {
                continue;
            }

            self::put($key, is_bool($value) ? ($value ? '1' : '0') : $value);
        }

        // The staff API caches the grouped settings; whichever screen saved, drop it.
        \Illuminate\Support\Facades\Cache::forget('tenant_settings_'.tenant('id'));
    }

    public static function put(string $key, mixed $value): void
    {
        if (self::isSecret($key) && is_string($value) && $value !== '') {
            $value = self::PREFIX.Crypt::encryptString($value);
        }

        DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now()]);
    }

    private static function reveal(string $key, mixed $value): mixed
    {
        if (! is_string($value) || ! str_starts_with($value, self::PREFIX)) {
            return $value;
        }

        try {
            return Crypt::decryptString(substr($value, strlen(self::PREFIX)));
        } catch (DecryptException) {
            report(new \RuntimeException("Setting [{$key}] could not be decrypted (was APP_KEY changed?)."));

            return null;
        }
    }
}
