<?php

namespace App\Modules\Shippings\Http\Requests;

use App\Modules\Shippings\Enums\ShippingStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListShippingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ShippingStatusEnum::class)],
        ];
    }
}
