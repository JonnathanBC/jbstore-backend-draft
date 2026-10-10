<?php

namespace App\Modules\Shippings\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'driver_id' => $this->driver_id,
            'driver' => $this->whenLoaded('driver', fn() => [
                'user' => $this->when(
                    $this->driver->relationLoaded('user'),
                    fn() => [
                        'id' => $this->driver->user->id,
                        'first_name' => trim(($this->driver->user->name ?? '')),
                        'last_name' => trim(($this->driver->user->last_name ?? '')),
                    ],
                ),
                'license_plate' => $this->driver->license_plate,
            ]),
            'status' => $this->status,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
