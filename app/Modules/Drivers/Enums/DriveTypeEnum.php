<?php

namespace App\Modules\Drivers\Enums;

enum DriveTypeEnum: string
{
    case Motorcycle = 'motorcycle';
    case Car = 'car';

    /**
     * Valores crudos para la migración.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
