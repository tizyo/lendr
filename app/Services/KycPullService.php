<?php

namespace App\Services;

use App\Models\Landlord\GhostUser;
use App\Models\Tenant\Borrower;

/**
 * Cross-tenant KYC lookup.
 *
 * When a tenant onboards a borrower, they can enter an NRC/TPIN/company reg
 * and this service returns any matching ghost user profile from the central DB.
 * The tenant then selects which fields to import into their borrower record.
 * No other tenant's data is exposed — only what the ghost user themselves provided.
 */
class KycPullService
{
    public function __construct(private GhostUserService $ghostUserService) {}

    /**
     * Lookup by NRC/TPIN/company reg. Returns a redacted profile if found.
     *
     * @return array{found: bool, ghost_user_id: int|null, fields: array}
     */
    public function lookup(
        ?string $nationalId = null,
        ?string $tpin = null,
        ?string $companyReg = null,
    ): array {
        $ghostUser = $this->ghostUserService->findByIdentifiers($nationalId, $tpin, $companyReg);

        if (! $ghostUser) {
            return ['found' => false, 'ghost_user_id' => null, 'fields' => []];
        }

        return [
            'found'         => true,
            'ghost_user_id' => $ghostUser->id,
            'fields'        => $this->buildFields($ghostUser),
        ];
    }

    /**
     * Import selected fields from a ghost user into a borrower record.
     * Only updates fields the staff explicitly selected.
     * Also links borrower.ghost_user_id.
     *
     * @param  string[]  $importFields  e.g. ['name', 'phone', 'email', 'address']
     */
    public function import(Borrower $borrower, int $ghostUserId, array $importFields): Borrower
    {
        $ghostUser = GhostUser::findOrFail($ghostUserId);

        $map = [
            'name'               => fn () => ['first_name' => $ghostUser->name],
            'phone'              => fn () => ['phone'      => $ghostUser->phone],
            'email'              => fn () => ['email'      => $ghostUser->email],
            'address'            => fn () => ['address'    => $ghostUser->address],
            'city'               => fn () => ['city'       => $ghostUser->city],
            'date_of_birth'      => fn () => ['date_of_birth' => $ghostUser->date_of_birth?->toDateString()],
            'gender'             => fn () => ['gender'     => $ghostUser->gender],
            'national_id'        => fn () => ['national_id' => $ghostUser->national_id],
            'tpin_number'        => fn () => ['tpin_number' => $ghostUser->tpin_number],
            'company_reg_number' => fn () => ['company_reg_number' => $ghostUser->company_reg_number],
        ];

        $updates = ['ghost_user_id' => $ghostUser->id];

        foreach ($importFields as $field) {
            if (isset($map[$field])) {
                $updates = array_merge($updates, ($map[$field])());
            }
        }

        $borrower->update($updates);

        return $borrower->fresh();
    }

    private function buildFields(GhostUser $user): array
    {
        $fields = [];

        if ($user->name)               $fields['name']               = $user->name;
        if ($user->phone)              $fields['phone']              = $user->phone;
        if ($user->email)              $fields['email']              = $user->email;
        if ($user->address)            $fields['address']            = $user->address;
        if ($user->city)               $fields['city']               = $user->city;
        if ($user->date_of_birth)      $fields['date_of_birth']      = $user->date_of_birth->toDateString();
        if ($user->gender)             $fields['gender']             = $user->gender;
        if ($user->national_id)        $fields['national_id']        = $user->national_id;
        if ($user->tpin_number)        $fields['tpin_number']        = $user->tpin_number;
        if ($user->company_reg_number) $fields['company_reg_number'] = $user->company_reg_number;

        return $fields;
    }
}
