<?php

namespace App\Modules\Shippings\Enums;

enum ShippingStatusEnum: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Valores crudos para la migración: ['pending', 'completed', ...].
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Transiciones permitidas desde este estado (máquina de estados de la orden).
     *
     * @return list<self>
     */
    // public function allowedTransitions(): array
    // {
    //     return match ($this) {
    //         self::Pending => [self::Processing],
    //         default => [],
    //     };
    // }

    // public function canTransitionTo(self $next): bool
    // {
    //     return in_array($next, $this->allowedTransitions(), true);
    // }
}
