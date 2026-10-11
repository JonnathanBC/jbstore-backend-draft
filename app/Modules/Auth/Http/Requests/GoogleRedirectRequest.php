<?php

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GoogleRedirectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Lo genera el front y lo guarda en una cookie: así ata el login a ESE navegador (anti login-CSRF)
            'state' => ['required', 'string', 'alpha_num', 'between:32,128'],
        ];
    }
}
