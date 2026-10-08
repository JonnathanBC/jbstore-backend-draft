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

    public function messages()
    {
        return [
            'type.required' => 'The type field is required.',
            'type.enum' => 'The type field must be a valid enum value.',
            'license_plate.required' => 'The license plate field is required.',
            'license_plate.string' => 'The license plate field must be a string.',
            'license_plate.max' => 'The license plate field must not be greater than 20 characters.',
            'license_plate.unique' => 'The license plate field must be unique.',
        ];
    }
}
