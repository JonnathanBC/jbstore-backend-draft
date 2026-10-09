<?php

namespace App\Modules\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CapturePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * El monto NO se recibe: se cobra el que quedó fijado en el pago al iniciar la sesión.
     */
    public function rules(): array
    {
        return [
            'purchaseNumber' => ['required', 'string', 'digits_between:1,12'],
            'transactionToken' => ['required', 'string'],
        ];
    }
}
