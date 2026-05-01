<?php

namespace App\Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Products\Models\Product;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $allowedSortable = ['updated_at'];
        $query = Product::query();

        return $this->paginated(
            $query,
            $request,
            $allowedSortable,
        );
    }

    public function store(Request $request)
    {
        $data = Product::create($request->all());

        return response()->json($data, 201);
    }

    public function show(Product $product)
    {
        return response()->json($product);
    }

    public function update(Request $request, Product $product)
    {
        $product->update($request->all());

        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(['message' => 'deleted successfully']);
    }
}
