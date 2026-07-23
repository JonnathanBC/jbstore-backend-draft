<?php

namespace App\Modules\Categories\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Categories\Models\Subcategory;
use App\Modules\Products\Models\Product;
use Illuminate\Http\Request;

class PublicSubcategoryController extends Controller
{
    public function show(Request $request, Subcategory $subcategory)
    {
        // Productos de la subcategoría: Product tiene subcategory_id directo,
        // así que un where simple alcanza (no hace falta whereHas).
        $query = Product::query()
            ->where('subcategory_id', $subcategory->id)
            ->when($request->input('orderBy') === 'relevant', function ($query) {
                $query->orderBy('created_at', 'desc');
            })->when($request->input('orderBy') === 'major_to_minor', function ($query) {
                $query->orderBy('price', 'desc');
            })->when($request->input('orderBy') === 'minor_to_major', function ($query) {
                $query->orderBy('price', 'asc');
            });

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'ilike', "%{$request->input('search')}%");
            });
        }

        $products = $query
            ->paginate($request->integer('per_page', 10))
            ->appends($request->query());

        return response()->json([
            'subcategory' => $subcategory,
            'products' => $products,
        ]);
    }
}
