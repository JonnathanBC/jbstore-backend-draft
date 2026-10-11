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

    /**
     * Transiciones permitidas desde este estado (máquina de estados de la orden).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        // Sin default: un estado nuevo sin transiciones definidas explota con UnhandledMatchError
        return match ($this) {
            self::Pending => [self::Processing, self::Cancelled],
            self::Processing => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Completed, self::Failed],
            self::Failed => [self::Refunded],
            self::Refunded => [self::Shipped, self::Cancelled],
            self::Completed => [self::Cancelled],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * Por qué no se permite la transición: la regla vive en allowedTransitions, acá solo el mensaje.
     */
    public function transitionErrorTo(self $next): string
    {
        return match (true) {
            $next === self::Cancelled && $this === self::Shipped
                => 'No se puede cancelar la orden porque tiene envíos pendientes.',
            $next === self::Cancelled && $this === self::Failed
                => 'No se puede cancelar la orden porque el producto no se ha retornado a la tienda.',
            default => "La orden no puede pasar de {$this->value} a {$next->value}.",
        };
    }
}
