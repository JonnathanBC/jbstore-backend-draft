<?php

namespace App\Modules\Drivers\Http\Requests;

use App\Modules\Drivers\Enums\DriveTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'type' => ['sometimes', 'required', Rule::enum(DriveTypeEnum::class)],
            'license_plate' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('drivers', 'license_plate')->ignore($this->route('driver')),
            ],
        ];
    }
}
