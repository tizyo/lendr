<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Generates the VAPID key pair the borrower PWA's push notifications are
 * signed with. Prints .env lines — it never edits .env itself. Generate once
 * per platform: changing the keys silently orphans every existing subscription.
 */
class GenerateWebPushKeysCommand extends Command
{
    protected $signature = 'lendr:webpush-keys {--subject=mailto:support@lendr.app : Contact URI push services can reach you on}';

    protected $description = 'Generate VAPID keys for borrower PWA push notifications';

    public function handle(): int
    {
        if (config('services.webpush.public_key')) {
            $this->warn('VAPID keys are already configured. Replacing them disconnects every subscribed phone until borrowers re-enable notifications.');
        }

        try {
            $keys = VAPID::createVapidKeys();
        } catch (\RuntimeException $e) {
            // Typical on Windows PHP: OpenSSL can't create EC keys without a config file.
            $this->error("Couldn't generate keys: {$e->getMessage()}");
            $this->line('On Windows, set OPENSSL_CONF to your PHP extras/ssl/openssl.cnf, or run this on the server.');

            return self::FAILURE;
        }

        $this->line('Add these to .env, then run `php artisan config:cache`:');
        $this->newLine();
        $this->line("VAPID_PUBLIC_KEY={$keys['publicKey']}");
        $this->line("VAPID_PRIVATE_KEY={$keys['privateKey']}");
        $this->line('VAPID_SUBJECT='.$this->option('subject'));

        return self::SUCCESS;
    }
}
