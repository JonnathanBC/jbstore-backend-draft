<?php

namespace App\Modules\Addresses\Models;

use App\Concerns\BelongsToUser;
use App\Modules\Addresses\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Address extends Model
{
    use BelongsToUser, HasFactory;

    protected static function newFactory()
    {
        return AddressFactory::new();
    }

    protected $fillable = [
        'address_line_1',
        'address_line_2',
        'province',
        'receiver',
        'receiver_info',
        'phone',
        'city',
        'postal_code',
        'country',
        'reference',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'receiver_info' => 'array',
        ];
    }

    /**
     * Marca esta dirección como predeterminada y desmarca las demás del usuario (una sola predeterminada por usuario).
     * Filtra por el user_id de la propia dirección: no depende del global scope,
     * así funciona igual desde un job o un comando (sin usuario autenticado).
     */
    public function markAsDefault(): void
    {
        DB::transaction(function () {
            static::where('user_id', $this->user_id)
                ->whereKeyNot($this->getKey())
                ->update(['is_default' => false]);

            $this->update(['is_default' => true]);
        });
    }
}
