<?php

namespace App\Modules\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CapturePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1'],
            'transactionToken' => ['required', 'string'],
            'customerEmail' => ['required', 'string'],
            'channel' => ['required', 'string'],
            'purchaseNumber' => ['required', 'string'],
        ];
    }
}
