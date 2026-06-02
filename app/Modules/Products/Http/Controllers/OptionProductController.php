<?php

namespace App\Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Products\Models\OptionProduct;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Services\VariantService;
use Illuminate\Http\Request;

class OptionProductController extends Controller
{
    public function __construct(private VariantService $variantService) {}

    public function store(Request $request)
    {
        $request->validate([
            'product_id'              => 'required|exists:products,id',
            'option_id'               => 'required|exists:options,id',
            'features'                => 'required|array|min:1',
            'features.*.id'          => 'required',
            'features.*.value'       => 'required',
            'features.*.description' => 'required',
        ]);

        $optionProduct = OptionProduct::create($request->only('product_id', 'option_id', 'features'));

        $this->variantService->generateForProduct(
            Product::find($request->product_id)
        );

        return response()->json($optionProduct, 201);
    }

    public function removeFeature(Request $request)
    {
        $request->validate([
            'option_id' => 'required|exists:options,id',
            'feature_id' => 'required',
        ]);

        $optionProduct = OptionProduct::where('option_id', $request->option_id)->firstOrFail();

        $features = collect($optionProduct->features)
            ->reject(fn($feature) => $feature['id'] == $request->feature_id)
            ->values()
            ->toArray();

        $optionProduct->update([
            'features' => $features,
        ]);

        $this->variantService->generateForProduct(
            Product::find($optionProduct->product_id)
        );

        return response()->json([
            'data' => $optionProduct->fresh(),
        ]);
    }

    public function removeOption(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'option_id' => 'required|exists:options,id',
        ]);

        $optionProduct = OptionProduct::where('option_id', $request->option_id)
            ->where('product_id', $request->product_id)
            ->firstOrFail();

        $optionProduct->delete();

        $this->variantService->generateForProduct(
            Product::find($request->product_id)
        );

        return response()->json([
            'success' => true,
        ]);
    }
}
