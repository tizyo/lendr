<?php

namespace App\Http\Controllers\Webhook;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/v1/webhooks/{provider}/{tenant} — hands a mobile-money callback to its
 * provider's handler once InitializeTenancyFromRoute has opened the workspace.
 * Airtel, MTN and Zamtel had handlers but no route at all before this.
 */
class ProviderWebhookController
{
    public const HANDLERS = [
        'flutterwave' => FlutterwaveWebhookController::class,
        'pawapay' => PawaPayWebhookController::class,
        'lipila' => LipilaWebhookController::class,
        'airtel' => AirtelWebhookController::class,
        'mtn' => MtnWebhookController::class,
        'zamtel' => ZamtelWebhookController::class,
    ];

    public function __invoke(Request $request, string $provider): Response
    {
        $handler = self::HANDLERS[$provider] ?? abort(404);

        return app($handler)->handle($request);
    }
}
