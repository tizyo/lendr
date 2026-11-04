<?php

namespace App\Http\Requests\Api\V1\Borrower;

use App\Http\Requests\Admin\Concerns\ValidatesBorrowerFields;
use App\Models\Tenant\Borrower;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A partial update (send only what changes) under the staff screen's rules, so
 * corporate borrowers' fields can be edited over the API too.
 */
class UpdateBorrowerRequest extends FormRequest
{
    use ValidatesBorrowerFields {
        isCorporateSubmission as private submittedAsCorporate;
    }

    private const ZAMBIAN_MOBILE = 'regex:/^(\+260|0)(9[5-7]|7[6-8]|6[5-7])\d{7}$/';

    public function authorize(): bool
    {
        return $this->user()->can('borrowers.edit'); // the seeded permission (there is no borrowers.update)
    }

    /** Without borrower_type in the request, the borrower keeps the type it has. */
    protected function isCorporateSubmission(): bool
    {
        return $this->has('borrower_type')
            ? $this->submittedAsCorporate()
            : $this->route('borrower')?->borrower_type === Borrower::TYPE_CORPORATE;
    }

    public function rules(): array
    {
        $rules = $this->borrowerRules($this->route('borrower'));
        $rules['phone'][] = self::ZAMBIAN_MOBILE;
        $rules['phone_alt'][] = self::ZAMBIAN_MOBILE;

        // Partial: a field is only checked when it is sent.
        return array_map(fn (array $r) => ['sometimes', ...$r], $rules);
    }

    /**
     * What to save: switching type clears the other type's fields; otherwise only
     * the fields sent change.
     *
     * @return array<string, mixed>
     */
    public function updateData(): array
    {
        $switching = $this->has('borrower_type')
            && $this->input('borrower_type') !== $this->route('borrower')?->borrower_type;

        return $switching ? $this->borrowerData() : $this->validated();
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Phone must be a valid Zambian number (e.g. +260971234567 or 0971234567).',
            'phone_alt.regex' => 'Alternate phone must be a valid Zambian number.',
        ];
    }
}
