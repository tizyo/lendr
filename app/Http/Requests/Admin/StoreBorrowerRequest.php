<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesBorrowerFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreBorrowerRequest extends FormRequest
{
    use ValidatesBorrowerFields;

    public function authorize(): bool
    {
        return $this->user()->can('borrowers.create');
    }

    public function rules(): array
    {
        return $this->borrowerRules();
    }
}
