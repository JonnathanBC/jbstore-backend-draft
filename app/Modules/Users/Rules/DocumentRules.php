<?php

namespace App\Modules\Users\Rules;

use Illuminate\Validation\Rule;

/**
 * Reglas de documento compartidas por el registro y el completar perfil:
 * si cambia el formato de la CI, cambia en un solo lugar.
 */
class DocumentRules
{
    public static function type(): array
    {
        return ['required', 'string', Rule::in(['PP', 'CI', 'RUC'])];
    }

    public static function number(?string $type): array
    {
        return [
            'required',
            'string',
            'max:255',
            Rule::when($type === 'CI', ['regex:/^\d{10}$/']),
            Rule::when($type === 'RUC', ['regex:/^\d{13}$/']),
            Rule::when($type === 'PP', ['regex:/^[A-Za-z0-9]+$/']),
        ];
    }
}
