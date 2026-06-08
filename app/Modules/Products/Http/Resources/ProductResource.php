<?php

namespace App\Modules\Products\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'price' => $this->price,
            'stock' => $this->stock,
            'subcategory_id' => $this->subcategory_id,
            'category' => $this->whenLoaded('subcategory', function () {
                return $this->subcategory?->category ? [
                    'id' => $this->subcategory->category->id,
                    'name' => $this->subcategory->category->name,
                    'family' => $this->subcategory->category->family ? [
                        'id' => $this->subcategory->category->family->id,
                        'name' => $this->subcategory->category->family->name,
                    ] : null,
                ] : null;
            }),
            'options' => $this->whenLoaded('options', function () {
                return $this->options->map(function ($option) {
                    return [
                        'id' => $option->id,
                        'name' => $option->name,
                        'type' => $option->type,
                        'features' => $option->pivot->features,
                    ];
                });
            }),
            'variants' => $this->whenLoaded('variants', function () {
                return $this->variants->map(function ($variant) {
                    return [
                        'id' => $variant->id,
                        'sku' => $variant->sku,
                        'image' => $variant->image,
                        'features' => $variant->features->map(function ($feature) {
                            return [
                                'id' => $feature->id,
                                'description' => $feature->description,
                            ];
                        }),
                    ];
                });
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
