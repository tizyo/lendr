<?php

namespace App\Http\Requests\Api\V1\Borrower;

use App\Http\Requests\Admin\Concerns\ValidatesBorrowerFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Same rules as the staff screen (individual or corporate borrower), plus a Zambian
 * mobile number check. This used to have its own individual-only copy, so a
 * corporate borrower couldn't be created over the API.
 */
class StoreBorrowerRequest extends FormRequest
{
    use ValidatesBorrowerFields;

    private const ZAMBIAN_MOBILE = 'regex:/^(\+260|0)(9[5-7]|7[6-8]|6[5-7])\d{7}$/';

    public function authorize(): bool
    {
        return $this->user()->can('borrowers.create');
    }

    /**
     * @bodyParam borrower_type            string optional individual (default) or corporate. Example: individual
     * @bodyParam first_name               string Required for individuals. Example: Chanda
     * @bodyParam last_name                string Required for individuals. Example: Mwale
     * @bodyParam company_name             string Required for corporates. Example: Mwale Trading Ltd
     * @bodyParam company_reg_number       string Required for corporates (PACRA). Example: 120190012345
     * @bodyParam contact_person_name      string Required for corporates. Example: Chanda Mwale
     * @bodyParam phone                    string required Zambian mobile number in E.164 or local format. Example: +260971234567
     */
    public function rules(): array
    {
        $rules = $this->borrowerRules();
        $rules['phone'][] = self::ZAMBIAN_MOBILE;
        $rules['phone_alt'][] = self::ZAMBIAN_MOBILE;

        return $rules;
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Phone must be a valid Zambian number (e.g. +260971234567 or 0971234567).',
            'phone_alt.regex' => 'Alternate phone must be a valid Zambian number.',
        ];
    }
}
