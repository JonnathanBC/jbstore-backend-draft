<?php

namespace App\Modules\Payments\Enums;

enum PaymentStatusEnum: string
{
    /** Sesión iniciada, todavía no se intentó cobrar. Es el único estado que permite cobrar. */
    case Pending = 'pending';

    /** La pasarela aprobó el cobro. */
    case Authorized = 'authorized';

    /** La pasarela rechazó el cobro (tarjeta, fondos, antifraude...). */
    case Rejected = 'rejected';

    /** No sabemos si se cobró (timeout, 5xx): NO se reintenta, se reconcilia. */
    case Error = 'error';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
