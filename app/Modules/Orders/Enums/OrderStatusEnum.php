<?php

namespace App\Modules\Orders\Enums;

enum OrderStatusEnum: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
    case Refunded = 'refunded';

    /**
     * Valores crudos para la migración: ['pending', 'processing', ...].
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
