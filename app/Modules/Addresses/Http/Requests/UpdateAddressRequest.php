<?php

namespace App\Modules\Addresses\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'address_line_1' => ['sometimes', 'required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'required', 'string', 'max:100'],
            'province' => ['sometimes', 'required', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['sometimes', 'required', 'string', 'size:2'],
            'reference' => ['nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'regex:/^\d{6,15}$/'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
