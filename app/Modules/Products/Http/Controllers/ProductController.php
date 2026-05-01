<?php

namespace App\Modules\Products\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
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
        $request->validate([
            'image' => 'required|file|max:1024',
            'sku' => 'required|unique:products,sku',
            'name' => 'required|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
        ]);


        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');

            $request['image_path'] = $path;
        }

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
