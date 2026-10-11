<?php

namespace App\Modules\Auth\Http\Requests;

use App\Modules\Users\Rules\DocumentRules;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'name'                  => 'required|string|max:255',
            'last_name'             => 'required|string|max:255',
            'document_type'         => DocumentRules::type(),
            'document_number'       => DocumentRules::number($this->document_type),
            'email'                 => 'required|string|email|max:255|unique:users',
            'phone' => [
                'required',
                'string',
                'regex:/^\d{6,15}$/',
            ],
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
        ];
    }

}
