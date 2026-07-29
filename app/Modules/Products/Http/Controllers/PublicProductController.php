<?php

namespace App\Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
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

    public function show(Product $product)
    {
        return response()->json($product);
    }
}
