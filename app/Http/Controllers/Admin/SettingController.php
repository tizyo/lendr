<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Mail\TenantMailService;
use App\Services\TenantLogo;
use App\Support\TenantSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(): Response
    {
        $settings = DB::table('settings')->get()->keyBy('key')->map(function ($row) {
            // A saved secret shows as the mask (sent back = unchanged); an empty one shows empty.
            return TenantSettings::isSecret($row->key) && filled($row->value) ? TenantSettings::MASK : $row->value;
        });

        return Inertia::render('settings/Index', [
            'settings' => $settings,
            'logoUrl' => TenantLogo::url(),
            // What each lender pastes into its mobile-money providers' dashboards.
            'webhookUrls' => tenant('id') ? collect(\App\Http\Controllers\Webhook\ProviderWebhookController::HANDLERS)
                ->keys()
                ->mapWithKeys(fn ($provider) => [$provider => route('api.v1.webhooks.tenant', ['provider' => $provider, 'tenant' => tenant('id')])])
                : (object) [],
        ]);
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        $request->validate(['logo' => TenantLogo::RULES], [
            'logo.mimes' => 'Upload a PNG, JPG or WebP image (SVG logos don\'t display in email).',
        ]);

        TenantLogo::store($request->file('logo'));

        return back()->with('success', 'Logo updated.');
    }

    public function deleteLogo(): RedirectResponse
    {
        TenantLogo::delete();

        return back()->with('success', 'Logo removed.');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['settings' => ['required', 'array']]);

        // Validates, encrypts secrets, keeps Enterprise-only providers to Enterprise.
        TenantSettings::save($data['settings']);

        return back()->with('success', 'Settings saved.');
    }

    public function testEmail(Request $request): RedirectResponse
    {
        $to = $request->user()->email;

        try {
            (new TenantMailService)->raw(
                $to,
                'LENDR — SMTP Test',
                'LENDR SMTP test — configuration is working.',
            );

            return back()->with('success', "Test email sent to {$to}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'SMTP error: '.$e->getMessage());
        }
    }
}
