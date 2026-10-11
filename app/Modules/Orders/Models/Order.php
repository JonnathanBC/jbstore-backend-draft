<?php

namespace App\Modules\Orders\Models;

use App\Concerns\BelongsToUser;
use App\Concerns\Scopes\OwnedByUserScope;
use App\Modules\Orders\Enums\OrderStatusEnum;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use BelongsToUser ,HasUuids;

    protected $fillable = [
        'user_id',
        'content',
        'address',
        'payment_id',
        'total',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'address' => 'array',
            'status' => OrderStatusEnum::class,
        ];
    }

    /**
     * El admin opera sobre órdenes de cualquier usuario: busca sin el scope de dueño.
     * Así ningún otro módulo necesita conocer OwnedByUserScope.
     */
    public static function findForAdminOrFail(string $id): self
    {
        return static::query()->forAdmin()->findOrFail($id);
    }

    /**
     * Sin el scope de dueño (que filtra por Auth::id() incluso si el que pide es el admin).
     * Sirve en queries y en relaciones de otros módulos: `belongsTo(Order::class)->forAdmin()`.
     * Usalo SOLO detrás de can:admin.
     */
    public function scopeForAdmin(Builder $query): Builder
    {
        return $query->withoutGlobalScope(OwnedByUserScope::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
