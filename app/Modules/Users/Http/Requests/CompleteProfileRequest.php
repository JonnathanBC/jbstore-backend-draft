<?php

namespace App\Modules\Users\Http\Requests;

use App\Modules\Users\Rules\DocumentRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'last_name' => ['required', 'string', 'max:255'],
            'document_type' => DocumentRules::type(),
            'document_number' => DocumentRules::number($this->input('document_type')),
            'phone' => [
                'required',
                'string',
                'regex:/^\d{6,15}$/',
                // La columna es unique: sin esto un teléfono repetido sería un 500
                Rule::unique('users', 'phone')->ignore($this->user()->id),
            ],
        ];
    }
}
