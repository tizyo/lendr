<?php

use App\Models\Tenant\Borrower;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

function rawNationalId(Borrower $borrower): ?string
{
    return DB::table('borrowers')->where('id', $borrower->id)->value('national_id');
}

it('stores the NRC encrypted and reads it back transparently', function () {
    $borrower = Borrower::factory()->create(['national_id' => '123456/78/1']);

    expect(rawNationalId($borrower))->not->toBe('123456/78/1')
        ->and(Crypt::decryptString(rawNationalId($borrower)))->toBe('123456/78/1')
        ->and($borrower->fresh()->national_id)->toBe('123456/78/1')
        ->and($borrower->fresh()->national_id_hash)->toBe(Borrower::nationalIdHash('123456/78/1'));
});

it('never exposes the hash in API output', function () {
    $borrower = Borrower::factory()->create(['national_id' => '123456/78/1']);

    expect($borrower->fresh()->toArray())->not->toHaveKey('national_id_hash');
});

it('hashes NRCs regardless of formatting', function () {
    expect(Borrower::nationalIdHash('123456/78/1'))
        ->toBe(Borrower::nationalIdHash(' 123456 78 1 '))
        ->not->toBe(Borrower::nationalIdHash('123456/78/2'))
        ->and(Borrower::nationalIdHash(''))->toBeNull();
});

it('clears the hash when the NRC is removed', function () {
    $borrower = Borrower::factory()->create(['national_id' => '123456/78/1']);

    $borrower->update(['national_id' => null]);

    expect($borrower->fresh()->national_id_hash)->toBeNull();
});

it('finds a borrower by full NRC in search', function () {
    $admin = actingAsAdmin();
    $match = Borrower::factory()->create(['national_id' => '654321/10/1']);
    Borrower::factory()->create(['national_id' => '111111/11/1']);

    $ids = $this->actingAs($admin)
        ->getJson(route('api.v1.borrowers.index', ['search' => '654321/10/1']))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$match->id]);
});

it('rejects a duplicate NRC with a validation error instead of a 500', function () {
    $admin = actingAsAdmin();
    Borrower::factory()->create(['national_id' => '123456/78/1']);

    $this->actingAs($admin)
        ->postJson(route('api.v1.borrowers.store'), [
            'first_name' => 'Chanda',
            'last_name' => 'Mulenga',
            'phone' => '0977000111',
            'national_id' => '123456 78 1', // same number, different formatting
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('national_id');
});

it('lets a borrower keep their own NRC on update', function () {
    $admin = actingAsAdmin();
    $borrower = Borrower::factory()->create(['national_id' => '123456/78/1']);

    $this->actingAs($admin)
        ->putJson(route('api.v1.borrowers.update', $borrower), [
            'first_name' => $borrower->first_name,
            'last_name' => $borrower->last_name,
            'phone' => $borrower->phone,
            'national_id' => '123456/78/1',
        ])
        ->assertOk();
});

it('migrates existing plaintext NRCs both ways', function () {
    $migration = require database_path('migrations/tenant/2026_10_28_110000_encrypt_borrower_national_ids.php');
    $borrower = Borrower::factory()->create(['national_id' => '222222/22/2']);

    $migration->down();
    expect(rawNationalId($borrower))->toBe('222222/22/2'); // plaintext again

    $migration->up();
    expect(rawNationalId($borrower))->not->toBe('222222/22/2')
        ->and($borrower->fresh()->national_id)->toBe('222222/22/2')
        ->and($borrower->fresh()->national_id_hash)->toBe(Borrower::nationalIdHash('222222/22/2'));
});

it('refuses to migrate when two borrowers share an NRC up to formatting', function () {
    $migration = require database_path('migrations/tenant/2026_10_28_110000_encrypt_borrower_national_ids.php');
    Borrower::factory()->create(['national_id' => '333333/33/3']);
    $migration->down();
    DB::table('borrowers')->insert(array_merge(
        Borrower::factory()->raw(['national_id' => '333333 33 3']),
        ['created_at' => now(), 'updated_at' => now()],
    ));

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'share an NRC');
});
