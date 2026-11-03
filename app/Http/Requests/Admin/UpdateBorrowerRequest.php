<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesBorrowerFields;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBorrowerRequest extends FormRequest
{
    use ValidatesBorrowerFields;

    public function authorize(): bool
    {
        return $this->user()->can('borrowers.edit');
    }

    public function rules(): array
    {
        return [
            ...$this->borrowerRules($this->route('borrower')),
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
