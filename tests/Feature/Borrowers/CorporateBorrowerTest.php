<?php

use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\User;
use Spatie\Permission\Models\Permission;

/*
 * SMEs and other organisations borrow too: the borrower form takes a corporate
 * type with company details and a contact person instead of personal details.
 */

function borrowerDesk(): User
{
    $user = User::factory()->create(['role' => UserRole::LoanOfficer, 'is_active' => true]);
    $permissions = ['borrowers.view', 'borrowers.create', 'borrowers.edit'];
    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $user->givePermissionTo($permissions);

    return $user;
}

it('creates a corporate borrower from company details', function () {
    $this->actingAs(borrowerDesk())->post(route('borrowers.store'), [
        'borrower_type' => 'corporate',
        'company_name' => 'Mwale Agro Supplies Ltd',
        'trading_name' => 'Mwale Agro',
        'business_type' => 'private_limited',
        'industry' => 'Agriculture',
        'company_reg_number' => '120200012345',
        'tpin_number' => '1002003004',
        'incorporation_date' => '2019-04-01',
        'contact_person_name' => 'Chanda Mwale',
        'contact_person_position' => 'Director',
        'phone' => '0977000111',
        'country' => 'ZM',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $borrower = Borrower::where('phone', '0977000111')->sole();

    expect($borrower->isCorporate())->toBeTrue()
        ->and($borrower->full_name)->toBe('Mwale Agro Supplies Ltd')
        // Statements, SMS and search read first_name, so it carries the company name.
        ->and($borrower->first_name)->toBe('Mwale Agro Supplies Ltd')
        ->and($borrower->last_name)->toBeNull()
        ->and($borrower->company_reg_number)->toBe('120200012345')
        ->and($borrower->contact_person_name)->toBe('Chanda Mwale')
        ->and($borrower->incorporation_date->toDateString())->toBe('2019-04-01');
});

it('requires the company name, PACRA number and contact person for a corporate', function () {
    $this->actingAs(borrowerDesk())->post(route('borrowers.store'), [
        'borrower_type' => 'corporate',
        'phone' => '0977000222',
    ])->assertSessionHasErrors(['company_name', 'company_reg_number', 'contact_person_name'])
        ->assertSessionDoesntHaveErrors(['first_name', 'last_name']);
});

it('still requires a first and last name for an individual', function () {
    $this->actingAs(borrowerDesk())->post(route('borrowers.store'), [
        'phone' => '0977000333',
    ])->assertSessionHasErrors(['first_name', 'last_name'])
        ->assertSessionDoesntHaveErrors(['company_name', 'company_reg_number']);
});

it('rejects a PACRA number that is already registered', function () {
    Borrower::factory()->corporate()->create(['company_reg_number' => '120200099999']);

    $this->actingAs(borrowerDesk())->post(route('borrowers.store'), [
        'borrower_type' => 'corporate',
        'company_name' => 'Copycat Ltd',
        'company_reg_number' => '120200099999',
        'contact_person_name' => 'Someone',
        'phone' => '0977000444',
    ])->assertSessionHasErrors('company_reg_number');
});

it('drops personal fields when a borrower is saved as a corporate', function () {
    $this->actingAs(borrowerDesk())->post(route('borrowers.store'), [
        'borrower_type' => 'corporate',
        'company_name' => 'Kafue Traders Ltd',
        'company_reg_number' => '120200055555',
        'contact_person_name' => 'Mutale Banda',
        'phone' => '0977000555',
        'gender' => 'female',
        'national_id' => '111111/11/1',
        'next_of_kin_name' => 'Should not stick',
    ])->assertSessionHasNoErrors();

    $borrower = Borrower::where('phone', '0977000555')->sole();

    expect($borrower->gender)->toBeNull()
        ->and($borrower->national_id)->toBeNull()
        ->and($borrower->next_of_kin_name)->toBeNull();
});

it('keeps individuals working as before and saves their TPIN on edit', function () {
    $borrower = Borrower::factory()->create(['first_name' => 'Alice', 'last_name' => 'Phiri']);

    $this->actingAs(borrowerDesk())->put(route('borrowers.update', $borrower), [
        ...$borrower->only('first_name', 'last_name', 'phone', 'country'),
        'tpin_number' => '2003004005',
        'is_active' => false,
    ])->assertSessionHasNoErrors();

    $borrower->refresh();

    expect($borrower->borrower_type)->toBe('individual')
        ->and($borrower->full_name)->toBe('Alice Phiri')
        ->and($borrower->tpin_number)->toBe('2003004005')
        ->and($borrower->is_active)->toBeFalse();
});

it('shows a corporate borrower with its company details', function () {
    $borrower = Borrower::factory()->corporate()->create(['company_name' => 'Ndola Hardware Ltd', 'business_type' => 'private_limited']);

    $this->actingAs(borrowerDesk())->get(route('borrowers.show', $borrower))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('borrower.full_name', 'Ndola Hardware Ltd')
            ->where('borrower.borrower_type', 'corporate')
            ->where('borrower.business_type_label', 'Private Limited Company'));
});

it('finds a corporate in the list by its PACRA number', function () {
    Borrower::factory()->corporate()->create(['company_reg_number' => '120200077777']);
    Borrower::factory()->count(3)->create();

    $this->actingAs(borrowerDesk())->get(route('borrowers.index', ['search' => '120200077777']))
        ->assertInertia(fn ($page) => $page->has('borrowers.data', 1)
            ->where('borrowers.data.0.borrower_type', 'corporate'));
});
