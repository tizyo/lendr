<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Mail\TenantMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SettingController extends BaseApiController
{
    // Grouped default settings with types
    private const DEFAULTS = [
        'general'  => ['company_name', 'company_phone', 'company_email', 'company_address', 'currency', 'timezone', 'date_format', 'fiscal_year_start'],
        'branding' => ['primary_color', 'secondary_color', 'company_logo_path', 'pwa_app_name', 'pwa_theme_color'],
        'smtp'     => ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_from_email', 'smtp_from_name', 'smtp_encryption'],
        'sms'      => ['sms_gateway', 'sms_sender_name'],
        'security' => ['require_2fa', 'session_timeout_minutes', 'password_expiry_days'],
        'pwa'      => ['pwa_app_name', 'pwa_theme_color', 'pwa_short_name'],
    ];

    // Keys that should never be returned in plaintext
    private const MASKED_KEYS = ['smtp_password', 'sms_api_key', 'mobile_money_secret'];

    public function index(Request $request): JsonResponse
    {
        $grouped = Cache::remember('tenant_settings_' . tenant('id'), 600, function () {
            $settings = DB::table('settings')->get()->keyBy('key');

            $grouped = [];
            foreach (self::DEFAULTS as $group => $keys) {
                foreach ($keys as $key) {
                    $row = $settings->get($key);
                    $grouped[$group][$key] = $row
                        ? (in_array($key, self::MASKED_KEYS) ? '••••••••' : $row->value)
                        : null;
                }
            }

            return $grouped;
        });

        return $this->success($grouped);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        foreach ($data['settings'] as $key => $value) {
            $masked = in_array($key, self::MASKED_KEYS) && $value === '••••••••';

            if ($masked) {
                continue; // Skip if client sent the masked placeholder back
            }

            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }

        $tenantId = tenant('id');
        Cache::forget("tenant_settings_{$tenantId}");
        Cache::forget("tenant_branding_{$tenantId}");

        return $this->success(null, 'Settings saved.');
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,png,svg', 'max:2048'],
        ]);

        $path = $request->file('logo')->store('logos', 's3');

        DB::table('settings')->updateOrInsert(
            ['key' => 'company_logo_path'],
            ['value' => $path, 'updated_at' => now()]
        );

        $tenantId = tenant('id');
        Cache::forget("tenant_settings_{$tenantId}");
        Cache::forget("tenant_branding_{$tenantId}");

        return $this->success(['url' => Storage::disk('s3')->url($path)], 'Logo uploaded.');
    }

    public function branding(): JsonResponse
    {
        $branding = Cache::remember('tenant_branding_' . tenant('id'), 600, function () {
            $keys = ['primary_color', 'secondary_color', 'company_logo_path', 'pwa_app_name', 'pwa_theme_color', 'company_name'];
            $rows = DB::table('settings')->whereIn('key', $keys)->pluck('value', 'key');

            return [
                'primary_color'   => $rows->get('primary_color', '#0D47A1'),
                'secondary_color' => $rows->get('secondary_color', '#1565C0'),
                'logo_url'        => $rows->get('company_logo_path')
                                        ? Storage::disk('s3')->url($rows->get('company_logo_path'))
                                        : null,
                'pwa_app_name'    => $rows->get('pwa_app_name', 'LENDR'),
                'pwa_theme_color' => $rows->get('pwa_theme_color', '#0D47A1'),
                'company_name'    => $rows->get('company_name', 'LENDR'),
            ];
        });

        return $this->success($branding);
    }

    public function testEmail(Request $request): JsonResponse
    {
        $request->validate(['email' => ['nullable', 'email']]);

        $to = $request->email ?? auth()->user()->email;

        try {
            (new TenantMailService)->raw($to, 'LENDR — SMTP Test Email', 'This is a test email from LENDR. Your SMTP configuration is working correctly.');

            return $this->success(null, "Test email sent to {$to}.");
        } catch (\Throwable $e) {
            return $this->error('Failed to send test email: '.$e->getMessage(), 500);
        }
    }
}
