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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}