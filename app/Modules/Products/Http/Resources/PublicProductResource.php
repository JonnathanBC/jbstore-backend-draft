<?php

namespace App\Modules\Products\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image,
            'price' => $this->price,
            'stock' => $this->stock,
            'variants' => $this->whenLoaded('variants', function () {
                return $this->variants->map(function ($variant) {
                    return [
                        'id' => $variant->id,
                        'image' => $variant->image,
                        'features' => $variant->features->map(function ($feature) {
                            return [
                                'id' => $feature->id,
                                'value' => $feature->value,
                                'option_id' => $feature->option_id,
                                'description' => $feature->description,
                            ];
                        }),
                    ];
                });
            }),
            'options' => $this->whenLoaded('options', function () {
                return $this->options->map(function ($option) {
                    return [
                        'id' => $option->id,
                        'name' => $option->name,
                        'type' => $option->type,
                        'features' => collect($option->pivot->features)->map(function ($feature) {
                            return [
                                'id' => $feature['id'],
                                'value' => $feature['value'],
                                'description' => $feature['description'],
                            ];
                        })->values(),
                    ];
                });
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
