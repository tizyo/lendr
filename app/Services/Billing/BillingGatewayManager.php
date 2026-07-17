<?php

namespace App\Services\Billing;

use App\Models\Landlord\BillingGatewayConfig;
use App\Services\Billing\Contracts\BillingGatewayInterface;
use App\Services\Billing\Gateways\FlutterwaveGateway;
use App\Services\Billing\Gateways\LipilaGateway;
use App\Services\Billing\Gateways\PawapayGateway;
use App\Services\Billing\Gateways\StripeGateway;
use RuntimeException;

class BillingGatewayManager
{
    /** Resolve the currently active gateway from DB config. */
    public function active(): BillingGatewayInterface
    {
        $config = BillingGatewayConfig::active();

        if (! $config) {
            throw new RuntimeException('No active billing gateway is configured. Please configure one in the landlord panel.');
        }

        return $this->make($config);
    }

    /** Resolve a specific gateway by name (for webhooks). */
    public function driver(string $gateway): BillingGatewayInterface
    {
        $config = BillingGatewayConfig::forGateway($gateway);

        if (! $config) {
            // Return a config-less instance for signature verification even if not yet saved
            $config = new BillingGatewayConfig(['gateway' => $gateway]);
        }

        return $this->make($config);
    }

    private function make(BillingGatewayConfig $config): BillingGatewayInterface
    {
        return match ($config->gateway) {
            'flutterwave' => new FlutterwaveGateway($config),
            'pawapay'     => new PawapayGateway($config),
            'lipila'      => new LipilaGateway($config),
            'stripe'      => new StripeGateway($config),
            default       => throw new RuntimeException("Unknown billing gateway: {$config->gateway}"),
        };
    }
}
