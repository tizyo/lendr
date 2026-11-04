<?php

use App\Support\TenantSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Provider credentials (mobile money, SMS, SMTP) were stored in plain text.
 * Encrypt the ones already saved; TenantSettings reads and writes them encrypted
 * from now on.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->whereIn('key', TenantSettings::SECRETS)->get(['key', 'value'])->each(function ($row) {
            if (! is_string($row->value) || $row->value === '' || str_starts_with($row->value, 'enc:')) {
                return;
            }

            DB::table('settings')->where('key', $row->key)->update(['value' => 'enc:'.Crypt::encryptString($row->value)]);
        });
    }

    public function down(): void
    {
        // Decrypting secrets back to plain text is deliberately not offered.
    }
};
