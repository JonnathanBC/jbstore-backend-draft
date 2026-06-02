<?php

namespace App\Modules\Products\Services;

use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Variant;

class VariantService
{
    public function generateForProduct(Product $product): void
    {
        $features = $product->options->pluck('pivot.features')->toArray();

        $product->variants()->delete();

        if (empty($features)) {
            return;
        }

        $combinations = $this->buildCombinations($features);

        foreach ($combinations as $combination) {
            $variant = Variant::create(['product_id' => $product->id]);
            $variant->features()->attach($combination);
        }
    }

    private function buildCombinations(array $arrays, int $index = 0, array $combination = []): array
    {
        if ($index === count($arrays)) {
            return [$combination];
        }

        $result = [];

        foreach ($arrays[$index] as $item) {
            $current = $combination;
            $current[] = $item['id'];
            $result = array_merge($result, $this->buildCombinations($arrays, $index + 1, $current));
        }

        return $result;
    }
}
