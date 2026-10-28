<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encrypts the NRC numbers held for guarantors, agents and investors (the
 * borrower ones were done in 2026_10_28_110000). These are never searched or
 * unique, so the models' `encrypted` cast is all that's needed — the columns
 * just have to fit the ciphertext.
 */
return new class extends Migration
{
    private const TABLES = ['guarantors', 'agents', 'investors'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'national_id')) {
                continue;
            }

            Schema::table($table, fn (Blueprint $t) => $t->text('national_id')->nullable()->change());

            DB::table($table)->whereNotNull('national_id')->where('national_id', '!=', '')
                ->orderBy('id')
                ->each(fn ($row) => DB::table($table)->where('id', $row->id)->update([
                    'national_id' => Crypt::encryptString($this->plaintext($row->national_id)),
                ]));
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'national_id')) {
                continue;
            }

            DB::table($table)->whereNotNull('national_id')->orderBy('id')
                ->each(fn ($row) => DB::table($table)->where('id', $row->id)->update([
                    'national_id' => $this->plaintext($row->national_id),
                ]));

            Schema::table($table, fn (Blueprint $t) => $t->string('national_id', 64)->nullable()->change());
        }
    }

    /** Re-running after a partial failure must not double-encrypt. */
    private function plaintext(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }
};
