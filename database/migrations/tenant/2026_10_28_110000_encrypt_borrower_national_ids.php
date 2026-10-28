<?php

use App\Models\Tenant\Borrower;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encrypts borrower NRC numbers at rest (Data Protection Act 2021 / scope 14.3).
 *
 * national_id becomes ciphertext (Borrower casts it as `encrypted`), and the
 * per-tenant uniqueness rule moves to national_id_hash, a keyed hash of the
 * normalised number. Requires APP_KEY to be stable: rotating it without
 * re-encrypting makes these values unreadable.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Work out every hash first, so a duplicate stops us before anything changes.
        $rows = DB::table('borrowers')->whereNotNull('national_id')->where('national_id', '!=', '')->get(['id', 'national_id']);

        $plain = $rows->mapWithKeys(fn ($r) => [$r->id => $this->plaintext($r->national_id)]);
        $hashes = $plain->map(fn ($v) => Borrower::nationalIdHash($v));

        $clashes = $hashes->filter()->duplicates();
        if ($clashes->isNotEmpty()) {
            $ids = $hashes->filter(fn ($h) => $clashes->contains($h))->keys()->implode(', ');

            throw new RuntimeException("Borrowers share an NRC once formatting is ignored (ids: {$ids}). Merge or correct them, then re-run the migration.");
        }

        // 2. Schema: ciphertext needs a text column; uniqueness moves to the hash.
        Schema::table('borrowers', function (Blueprint $table) {
            $table->dropUnique(['national_id']);
        });

        Schema::table('borrowers', function (Blueprint $table) {
            $table->text('national_id')->nullable()->change();
            $table->string('national_id_hash', 64)->nullable()->after('national_id');
        });

        // 3. Encrypt in place.
        foreach ($plain as $id => $value) {
            DB::table('borrowers')->where('id', $id)->update([
                'national_id' => Crypt::encryptString($value),
                'national_id_hash' => $hashes[$id],
            ]);
        }

        Schema::table('borrowers', function (Blueprint $table) {
            $table->unique('national_id_hash');

            if (Schema::hasColumn('borrowers', 'national_id_encrypted')) {
                $table->dropColumn('national_id_encrypted'); // never used
            }
        });
    }

    public function down(): void
    {
        $rows = DB::table('borrowers')->whereNotNull('national_id')->get(['id', 'national_id']);

        Schema::table('borrowers', function (Blueprint $table) {
            $table->dropUnique(['national_id_hash']);
            $table->dropColumn('national_id_hash');
            $table->string('national_id_encrypted')->nullable();
        });

        foreach ($rows as $row) {
            DB::table('borrowers')->where('id', $row->id)->update(['national_id' => $this->plaintext($row->national_id)]);
        }

        Schema::table('borrowers', function (Blueprint $table) {
            $table->string('national_id')->nullable()->change();
            $table->unique('national_id');
        });
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
