<?php

namespace App\Modules\Products\Services;

use App\Modules\Products\Models\Feature;
use App\Modules\Products\Models\Option;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Variant;
use Illuminate\Database\Eloquent\Collection;

class ProductsService
{
    public function getAllProducts(): Collection
    {
        return Product::with(['variants.features', 'options'])->get();
    }

    public function getProductById(int $id): ?Product
    {
        return Product::with(['variants.features', 'options'])->find($id);
    }

    public function createProduct(array $data): Product
    {
        return Product::create($data);
    }

    public function updateProduct(Product $product, array $data): Product
    {
        $product->update($data);
        return $product->fresh(['variants.features', 'options']);
    }

    public function deleteProduct(Product $product): bool
    {
        return $product->delete();
    }

    public function getAllVariants(): Collection
    {
        return Variant::with(['product', 'features.option'])->get();
    }

    public function getVariantById(int $id): ?Variant
    {
        return Variant::with(['product', 'features.option'])->find($id);
    }

    public function createVariant(array $data): Variant
    {
        return Variant::create($data);
    }

    public function updateVariant(Variant $variant, array $data): Variant
    {
        $variant->update($data);
        return $variant->fresh(['product', 'features.option']);
    }

    public function deleteVariant(Variant $variant): bool
    {
        return $variant->delete();
    }

    public function getAllOptions(): Collection
    {
        return Option::with('features')->get();
    }

    public function getOptionById(int $id): ?Option
    {
        return Option::with('features')->find($id);
    }

    public function createOption(array $data): Option
    {
        return Option::create($data);
    }

    public function updateOption(Option $option, array $data): Option
    {
        $option->update($data);
        return $option->fresh(['features']);
    }

    public function deleteOption(Option $option): bool
    {
        return $option->delete();
    }

    public function getAllFeatures(): Collection
    {
        return Feature::with(['option', 'variants'])->get();
    }

    public function getFeatureById(int $id): ?Feature
    {
        return Feature::with(['option', 'variants'])->find($id);
    }

    public function createFeature(array $data): Feature
    {
        return Feature::create($data);
    }

    public function updateFeature(Feature $feature, array $data): Feature
    {
        $feature->update($data);
        return $feature->fresh(['option', 'variants']);
    }

    public function deleteFeature(Feature $feature): bool
    {
        return $feature->delete();
    }
}
