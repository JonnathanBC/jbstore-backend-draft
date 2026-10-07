<?php

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\Enums\OrderStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(OrderStatusEnum::class)],
        ];
    }
}
