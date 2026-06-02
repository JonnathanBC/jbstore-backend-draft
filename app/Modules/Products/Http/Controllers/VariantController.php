<?php

namespace App\Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Services\VariantService;

class VariantController extends Controller
{
    public function __construct(private VariantService $variantService) {}

    public function generate(Product $product)
    {
        $this->variantService->generateForProduct($product);

        return response()->json(['message' => 'Combinaciones generadas exitosamente']);
    }
}
