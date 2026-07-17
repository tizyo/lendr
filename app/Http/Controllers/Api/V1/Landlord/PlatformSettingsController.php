<?php

namespace App\Http\Controllers\Api\V1\Landlord;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Landlord\PlatformBranding;
use App\Models\Landlord\PlatformEmailConfig;
use App\Models\Landlord\PlatformSmsConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class PlatformSettingsController extends BaseApiController
{
    // ═══════════════════════════════════════════════════════════════════════════
    // SMS
    // ═══════════════════════════════════════════════════════════════════════════

    public function smsIndex(): JsonResponse
    {
        $configs = collect(['africas_talking', 'sms_to'])->map(function ($provider) {
            $record = PlatformSmsConfig::where('provider', $provider)->first();

            return [
                'provider'      => $provider,
                'label'         => $provider === 'africas_talking' ? "Africa's Talking" : 'SMS.to',
                'is_active'     => $record?->is_active ?? false,
                'is_configured' => $record?->isConfigured() ?? false,
                'has_api_key'   => ! empty($record?->api_key),
                'sender_id'     => $record?->sender_id,
                'username'      => $record?->username,
                'sandbox'       => $record?->sandbox ?? false,
            ];
        });

        return $this->success($configs);
    }

    public function smsUpdate(Request $request, string $provider): JsonResponse
    {
        $this->validateProvider($provider, ['africas_talking', 'sms_to']);

        $rules = [
            'sender_id' => ['nullable', 'string', 'max:32'],
            'sandbox'   => ['nullable', 'boolean'],
        ];

        if ($provider === 'africas_talking') {
            $rules['username'] = ['nullable', 'string', 'max:128'];
        }

        // Only validate api_key if provided (allows updating other fields without revealing key)
        if ($request->filled('api_key')) {
            $rules['api_key'] = ['required', 'string', 'max:512'];
        }

        $data = $request->validate($rules);

        $config = PlatformSmsConfig::firstOrCreate(['provider' => $provider]);

        $update = array_filter([
            'sender_id' => $data['sender_id'] ?? null,
            'username'  => $data['username'] ?? null,
            'sandbox'   => $data['sandbox'] ?? false,
        ], fn ($v) => $v !== null);

        if ($request->filled('api_key')) {
            $update['api_key'] = $data['api_key'];
        }

        $config->update($update);

        return $this->success(null, 'SMS provider updated.');
    }

    public function smsActivate(string $provider): JsonResponse
    {
        $this->validateProvider($provider, ['africas_talking', 'sms_to']);

        $config = PlatformSmsConfig::where('provider', $provider)->first();

        if (! $config || ! $config->isConfigured()) {
            return $this->error('Configure API key before activating.', 422);
        }

        $config->activate();

        return $this->success(null, ucfirst(str_replace('_', ' ', $provider)) . ' is now the active SMS provider.');
    }

    public function smsDeactivate(string $provider): JsonResponse
    {
        $this->validateProvider($provider, ['africas_talking', 'sms_to']);

        PlatformSmsConfig::where('provider', $provider)->update(['is_active' => false]);

        return $this->success(null, 'SMS provider deactivated.');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Email
    // ═══════════════════════════════════════════════════════════════════════════

    public function emailIndex(): JsonResponse
    {
        $config = PlatformEmailConfig::active()
            ?? PlatformEmailConfig::first();

        if (! $config) {
            return $this->success([
                'configured'   => false,
                'is_active'    => false,
                'host'         => null,
                'port'         => 587,
                'encryption'   => 'tls',
                'username'     => null,
                'from_address' => null,
                'from_name'    => null,
                'has_password' => false,
            ]);
        }

        return $this->success([
            'configured'   => $config->isConfigured(),
            'is_active'    => $config->is_active,
            'host'         => $config->host,
            'port'         => $config->port,
            'encryption'   => $config->encryption,
            'username'     => $config->username,
            'from_address' => $config->from_address,
            'from_name'    => $config->from_name,
            'has_password' => ! empty($config->password),
        ]);
    }

    public function emailUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'host'         => ['nullable', 'string', 'max:255'],
            'port'         => ['nullable', 'integer', 'between:1,65535'],
            'encryption'   => ['nullable', 'in:tls,ssl,'],
            'username'     => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name'    => ['nullable', 'string', 'max:128'],
        ]);

        $config = PlatformEmailConfig::firstOrCreate([]);

        $update = array_filter($data, fn ($v) => $v !== null);

        if ($request->filled('password')) {
            $request->validate(['password' => ['string', 'max:512']]);
            $update['password'] = $request->password;
        }

        $config->update($update);

        // Auto-activate if not already active
        if (! $config->is_active && $config->isConfigured()) {
            $config->update(['is_active' => true]);
        }

        return $this->success(null, 'Platform email config updated.');
    }

    public function emailTest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to' => ['required', 'email'],
        ]);

        $config = PlatformEmailConfig::active();

        if (! $config || ! $config->isConfigured()) {
            return $this->error('No active platform email config found.', 422);
        }

        try {
            $mailer = Mail::build($config->toMailerConfig());
            $mailer->raw(
                'LENDR platform SMTP test — configuration is working.',
                fn ($m) => $m->to($data['to'])->subject('LENDR — Platform SMTP Test')
            );

            return $this->success(null, "Test email sent to {$data['to']}.");
        } catch (\Throwable $e) {
            return $this->error('SMTP error: ' . $e->getMessage(), 422);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Branding
    // ═══════════════════════════════════════════════════════════════════════════

    public function brandingIndex(): JsonResponse
    {
        $b = PlatformBranding::current() ?? new PlatformBranding();

        return $this->success([
            'company_name'   => $b->company_name   ?? 'LENDR',
            'tagline'        => $b->tagline,
            'address'        => $b->address,
            'phone'          => $b->phone,
            'email'          => $b->email,
            'website'        => $b->website,
            'primary_color'  => $b->primary_color  ?? '#059669',
            'invoice_footer' => $b->invoice_footer,
            'email_footer'   => $b->email_footer,
            'logo_url'       => $b->exists ? $b->logoUrl()    : null,
            'favicon_url'    => $b->exists ? $b->faviconUrl() : null,
        ]);
    }

    public function brandingUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_name'   => ['nullable', 'string', 'max:128'],
            'tagline'        => ['nullable', 'string', 'max:255'],
            'address'        => ['nullable', 'string', 'max:500'],
            'phone'          => ['nullable', 'string', 'max:32'],
            'email'          => ['nullable', 'email', 'max:255'],
            'website'        => ['nullable', 'url', 'max:255'],
            'primary_color'  => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'invoice_footer' => ['nullable', 'string', 'max:1000'],
            'email_footer'   => ['nullable', 'string', 'max:1000'],
        ]);

        $branding = PlatformBranding::current() ?? PlatformBranding::firstOrCreate([]);
        $branding->update(array_filter($data, fn ($v) => $v !== null));

        return $this->success(null, 'Branding updated.');
    }

    public function brandingUploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
        ]);

        $branding = PlatformBranding::current() ?? PlatformBranding::firstOrCreate([]);

        // Delete old logo if present
        if ($branding->logo_path) {
            Storage::disk('public')->delete($branding->logo_path);
        }

        $path = $request->file('logo')->store('branding', 'public');
        $branding->update(['logo_path' => $path]);

        return $this->success(['logo_url' => $branding->logoUrl()], 'Logo uploaded.');
    }

    public function brandingUploadFavicon(Request $request): JsonResponse
    {
        $request->validate([
            'favicon' => ['required', 'image', 'mimes:png,jpg,jpeg,ico,webp', 'max:512'],
        ]);

        $branding = PlatformBranding::current() ?? PlatformBranding::firstOrCreate([]);

        if ($branding->favicon_path) {
            Storage::disk('public')->delete($branding->favicon_path);
        }

        $path = $request->file('favicon')->store('branding', 'public');
        $branding->update(['favicon_path' => $path]);

        return $this->success(['favicon_url' => $branding->faviconUrl()], 'Favicon uploaded.');
    }

    public function brandingDeleteLogo(): JsonResponse
    {
        $branding = PlatformBranding::current();

        if ($branding?->logo_path) {
            Storage::disk('public')->delete($branding->logo_path);
            $branding->update(['logo_path' => null]);
        }

        return $this->success(null, 'Logo removed.');
    }

    public function brandingDeleteFavicon(): JsonResponse
    {
        $branding = PlatformBranding::current();

        if ($branding?->favicon_path) {
            Storage::disk('public')->delete($branding->favicon_path);
            $branding->update(['favicon_path' => null]);
        }

        return $this->success(null, 'Favicon removed.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function validateProvider(string $provider, array $allowed): void
    {
        if (! in_array($provider, $allowed)) {
            abort(404, "Unknown provider: {$provider}");
        }
    }
}
