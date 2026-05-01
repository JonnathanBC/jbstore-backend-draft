<?php

namespace App\Modules\Categories\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Categories\Events\SubcategoryDeleting;
use App\Modules\Categories\Models\Subcategory;
use Illuminate\Http\Request;

class SubcategoryController extends Controller
{
    public function index(Request $request)
    {
        $allowedSortable = ['updated_at'];

        $query = Subcategory::with('category.family');

        // Filtros dinámicos
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
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
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id'
        ]);

        $data = Subcategory::create($request->all());
        return response()->json($data, 201);
    }

    public function show(Subcategory $subcategory)
    {
        return response()->json($subcategory->load('category'));
    }

    public function update(Request $request, Subcategory $subcategory)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'category_id' => 'sometimes|required|exists:categories,id'
        ]);

        $subcategory->update($request->all());
        return response()->json($subcategory);
    }

    public function destroy(Subcategory $subcategory)
    {
        event(new SubcategoryDeleting($subcategory));

        $subcategory->delete();
        return response()->json(['message' => 'deleted successfully']);
    }
}
