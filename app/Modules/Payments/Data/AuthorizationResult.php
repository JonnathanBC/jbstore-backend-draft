<?php

namespace App\Modules\Payments\Data;

/**
 * Resultado de autorizar un cobro. El resto del módulo trabaja con esto,
 * nunca con el array crudo de Niubiz.
 */
final readonly class AuthorizationResult
{
    private function __construct(
        public bool $approved,
        public bool $rejected,
        public ?string $transactionId = null,
        public ?string $cardMasked = null,
        public ?string $cardBrand = null,
        public ?string $message = null,
        /** Respuesta de la pasarela SIN datos sensibles: es lo que se devuelve al front */
        public ?array $response = null,
    ) {}

    public static function approved(string $transactionId, ?string $cardMasked, ?string $cardBrand, array $response): self
    {
        return new self(true, false, $transactionId, $cardMasked, $cardBrand, null, $response);
    }

    public static function rejected(string $message, ?string $cardMasked, ?string $cardBrand, array $response): self
    {
        return new self(false, true, null, $cardMasked, $cardBrand, $message, $response);
    }

    /** No sabemos si cobró (timeout, error 5xx, respuesta ilegible). */
    public static function unknown(string $message): self
    {
        return new self(false, false, message: $message);
    }

    public function isUnknown(): bool
    {
        return ! $this->approved && ! $this->rejected;
    }
}
