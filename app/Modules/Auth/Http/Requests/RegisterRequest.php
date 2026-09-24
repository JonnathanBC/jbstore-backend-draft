<?php

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'name'                  => 'required|string|max:255',
            'last_name'             => 'required|string|max:255',
            'document_type'         => ['required','string',Rule::in(['PP', 'CI', 'RUC'])],
            'document_number'       => [
                'required',
                'string',
                'max:255',
                Rule::when(
                    $this->document_type === 'CI',
                    ['regex:/^\d{10}$/']
                ),
                Rule::when(
                    $this->document_type === 'RUC',
                    ['regex:/^\d{13}$/']
                ),
                Rule::when(
                    $this->document_type === 'PP',
                    ['regex:/^[A-Za-z0-9]+$/']
                ),
            ],
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
