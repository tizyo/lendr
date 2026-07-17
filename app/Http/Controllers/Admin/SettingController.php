<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Mail\TenantMailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    private const MASKED_KEYS = ['smtp_password', 'sms_api_key', 'mobile_money_secret'];

    public function index(): Response
    {
        $settings = DB::table('settings')->get()->keyBy('key')->map(function ($row) {
            return in_array($row->key, self::MASKED_KEYS) ? '••••••••' : $row->value;
        });

        return Inertia::render('settings/Index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['settings' => ['required', 'array']]);

        foreach ($data['settings'] as $key => $value) {
            if (in_array($key, self::MASKED_KEYS) && $value === '••••••••') {
                continue;
            }

            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }

        return back()->with('success', 'Settings saved.');
    }

    public function testEmail(Request $request): RedirectResponse
    {
        $to = $request->user()->email;

        try {
            (new TenantMailService)->raw(
                $to,
                'LENDR — SMTP Test',
                'LENDR SMTP test — configuration is working.'
            );

            return back()->with('success', "Test email sent to {$to}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'SMTP error: ' . $e->getMessage());
        }
    }
}
