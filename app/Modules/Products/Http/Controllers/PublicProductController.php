<?php

namespace App\Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Products\Http\Resources\PublicProductResource;
use App\Modules\Products\Models\Product;
use Illuminate\Http\Request;

class PublicProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()
                ->orderBy('created_at', 'desc')
                ->take(12);

        $request = $request->merge([
            'per_page' => 12,
        ]);

        return $this->paginated(
            $query,
            $request,
            ['updated_at', 'price'],
        );
    }

    public function byIds(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:50'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        $products = Product::query()
            ->whereIn('id', array_unique($validated['ids']))
            ->get();

        return PublicProductResource::collection($products);
    }

    public function show(Product $product)
    {
        return new PublicProductResource($product->load(['variants', 'options']));
    }
}
