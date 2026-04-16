<?php

namespace App\Modules\Categories\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Categories\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(Category::with('family')->paginate());
    }

    public function store(Request $request)
    {
        $data = Category::create($request->all());
        return response()->json($data, 201);
    }

    public function show(Category $category)
    {
        return response()->json($category->load('subcategories'));
    }

    public function update(Request $request, Category $category)
    {
        $category->update($request->all());
        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return response()->json(['message' => 'deleted successfully']);
    }
}