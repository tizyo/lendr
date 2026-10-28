<?php

namespace App\Rules;

use App\Models\Tenant\Borrower;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * NRC must be unique per tenant. The column is encrypted, so this compares
 * keyed hashes (formatting-insensitive) instead of `unique:borrowers`.
 */
class UniqueNationalId implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreBorrowerId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $hash = Borrower::nationalIdHash(is_string($value) ? $value : null);

        if (! $hash) {
            return;
        }

        $taken = Borrower::withTrashed()
            ->where('national_id_hash', $hash)
            ->when($this->ignoreBorrowerId, fn ($q, $id) => $q->whereKeyNot($id))
            ->exists();

        if ($taken) {
            $fail('Another borrower already has this NRC number.');
        }
    }
}
