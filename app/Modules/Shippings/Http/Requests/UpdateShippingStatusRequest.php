<?php

namespace App\Modules\Shippings\Http\Requests;

use App\Modules\Shippings\Enums\ShippingStatusEnum;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShippingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ShippingStatusEnum::class)],
        ];
    }
}
