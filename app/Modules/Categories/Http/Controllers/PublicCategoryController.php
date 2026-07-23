<?php

namespace App\Modules\Categories\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Categories\Models\Category;
use App\Modules\Products\Models\Product;
use Illuminate\Http\Request;

class PublicCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::with('family', 'subcategories');

        if ($request->filled('family_id')) {
            $query->where('family_id', $request->input('family_id'));
        }

        return $this->paginated(
            $query,
            $request,
            ['updated_at'],
        );
    }

    public function show(Request $request, Category $category)
    {

        // Productos de la categoría: los que tienen una subcategoría
        // que pertenece a esta categoría (Product -> subcategory -> category).
        $query = Product::when(
            $category->id, function($query) use ($category) {
               $query->whereHas('subcategory', function ($query) use ($category) {
                    $query->where('category_id', $category->id);
                });
            }
        )->when($request->input('orderBy') === 'relevant', function ($query) {
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

        // Devolvemos la categoría (para el "Resultados de {name}") y sus
        // productos paginados en una sola respuesta.
        return response()->json([
            'category' => $category,
            'products' => $products,
        ]);
    }
}
