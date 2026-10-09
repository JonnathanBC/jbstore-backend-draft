<?php

namespace App\Modules\Shippings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateShippingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'driver_id' => ['required', 'exists:drivers,id'],
        ];
    }
}
