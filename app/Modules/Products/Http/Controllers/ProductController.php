<?php

namespace App\Modules\Products\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Http\Resources\ProductResource;
use Illuminate\Support\Facades\Storage;

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
            'subcategory_id' => 'required|exists:subcategories,id',
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
        $product->setAttribute('image_path', Storage::url($product->getAttribute('image_path')));

        return response()->json(new ProductResource($product->load('subcategory.category.family')));
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'image' => 'nullable|image|max:1024',
            'sku' => 'required|unique:products,sku,' . $product->getAttribute('id'),
            'name' => 'required|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'subcategory_id' => 'required|exists:subcategories,id',
        ]);

        if ($request->hasFile('image')) {
            Storage::delete($product->getAttribute('image_path'));
            $path = $request->file('image')->store('products');
            $request['image_path'] = $path;
        }

        $product->update($request->all());
        $product['image_path'] = Storage::url($product->getAttribute('image_path'));

        return response()->json($product, 200);
    }

    public function destroy(Product $product)
    {
        Storage::delete($product->getAttribute('image_path'));
        $product->delete();
        return response()->json(['message' => 'Eliminado correctamente.']);
    }
}
