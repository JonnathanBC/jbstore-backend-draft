<?php

namespace App\Modules\Payments\Models;

use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Data\AuthorizationResult;
use App\Modules\Payments\Enums\PaymentStatusEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUuids;

    // `status` y los datos de la pasarela NO son asignables: solo cambian por los métodos de abajo
    protected $fillable = [
        'user_id',
        'purchase_number',
        'amount',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaymentStatusEnum::class,
            'provider_response' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatusEnum::Pending;
    }

    /** Compara contra un total recalculado del carrito, a 2 decimales. */
    public function hasAmount(float $total): bool
    {
        return $this->amount === number_format($total, 2, '.', '');
    }

    public function markAsAuthorized(AuthorizationResult $result): void
    {
        $this->status = PaymentStatusEnum::Authorized;
        $this->fillFromResult($result);
        $this->save();
    }

    public function markAsRejected(AuthorizationResult $result): void
    {
        $this->status = PaymentStatusEnum::Rejected;
        $this->fillFromResult($result);
        $this->save();
    }

    public function markAsError(string $message): void
    {
        $this->status = PaymentStatusEnum::Error;
        $this->error_message = $message;
        $this->save();
    }

    public function attachOrder(Order $order): void
    {
        $this->order_id = $order->id;
        $this->save();
    }

    private function fillFromResult(AuthorizationResult $result): void
    {
        $this->provider_transaction_id = $result->transactionId;
        $this->card_masked = $result->cardMasked;
        $this->card_brand = $result->cardBrand;
        $this->error_message = $result->message;
        $this->provider_response = $result->response;
    }
}
