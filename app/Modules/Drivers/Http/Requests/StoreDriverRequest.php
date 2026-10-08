<?php

namespace App\Modules\Drivers\Http\Requests;

use App\Modules\Drivers\Enums\DriveTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', Rule::enum(DriveTypeEnum::class)],
            'license_plate' => ['required', 'string', 'max:20', 'unique:drivers,license_plate'],
        ];
    }
}
