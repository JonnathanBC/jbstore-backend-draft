<?php

namespace App\Modules\Shippings\Models;

use App\Concerns\Scopes\OwnedByUserScope;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Orders\Models\Order;
use App\Modules\Shippings\Enums\ShippingStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipping extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'driver_id',
        'status',
        'refunded_at',
        'delivered_at',
    ];

    protected $casts = [
        'status' => ShippingStatusEnum::class,
        'refunded_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public static function findForAdminOrFail(string $id): self
    {
        return static::withoutGlobalScope(OwnedByUserScope::class)->findOrFail($id);
    }

    /**
     * Filtros del listado: `search` (placa o nombre del conductor) y `status` (exacto).
     *
     * @param  array{search?: ?string, status?: ?string}  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;

        // Los vacíos se ignoran: el front manda el param solo cuando hay filtro
        return $query
            ->when($search !== '', function (Builder $q) use ($search) {
                // Escapamos los comodines para que "%" o "_" se busquen literal
                $like = '%' . addcslashes($search, '\\%_') . '%';

                $q->whereHas('driver', function (Builder $driver) use ($like) {
                    $driver->where('license_plate', 'ilike', $like)
                        ->orWhereHas('user', function (Builder $user) use ($like) {
                            $user->where('name', 'ilike', $like)
                                ->orWhere('last_name', 'ilike', $like)
                                ->orWhereRaw("(name || ' ' || last_name) ilike ?", [$like]);
                        });
                });
            })
            ->when(filled($status), fn (Builder $q) => $q->where('status', $status));
    }

    public function order()
    {
        // Los envíos son del admin: la orden es de OTRO usuario y el scope de dueño la ocultaría (null)
        return $this->belongsTo(Order::class)->forAdmin();
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
