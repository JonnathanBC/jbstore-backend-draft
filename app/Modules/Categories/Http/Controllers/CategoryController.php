<?php

namespace App\Modules\Categories\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Categories\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $allowedSortable = ['updated_at'];

        $query = Category::with('family');

        // Filtros dinámicos
        if ($request->filled('family_id')) {
            $query->where('family_id', $request->input('family_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%");
            });
        }

        return $this->paginated(
            $query,
            $request,
            $allowedSortable,
        );
}

    public function store(Request $request)
    {
        $request->validate([
            'family_id' => 'required|exists:families,id',
            'name' => 'required',
        ]);
        $category = Category::create($request->all());
        return response()->json($category, 201);
    }

    public function show(Category $category)
    {
        return response()->json($category->load('subcategories'));
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'family_id' => 'required|exists:families,id',
            'name' => 'required',
        ]);

        $category->update($request->all());
        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return response()->json(['message' => 'deleted successfully']);
    }
}
