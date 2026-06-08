<?php

namespace App\Modules\Products\Services;

use Illuminate\Http\Request;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Variant;
use Illuminate\Support\Facades\Storage;

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

    public function updateVariant(Request $request, Variant $variant)
    {
        $request->validate([
            'image' => 'nullable|image|max:1024',
            'sku' => 'nullable|numeric|min:0',
            'stock' => 'nullable|numeric|min:0',
        ]);

        if ($request->hasFile('image')) {
            Storage::delete($variant->getAttribute('image_path'));
            $path = $request->file('image')->store('variants');
            $request['image_path'] = $path;
        }

        $variant->update($request->all());
        $variant['image_path'] = Storage::url($variant->getAttribute('image_path'));

        return response()->json($variant, 200);
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
