<?php

namespace App\Modules\Categories\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Categories\Models\Subcategory;
use Illuminate\Http\Request;

class SubcategoryController extends Controller
{
    public function index()
    {
        return response()->json(Subcategory::with('category')->paginate());
    }

    public function store(Request $request)
    {
        $data = Subcategory::create($request->all());
        return response()->json($data, 201);
    }

    public function show(Subcategory $subcategory)
    {
        return response()->json($subcategory->load('category'));
    }

    public function update(Request $request, Subcategory $subcategory)
    {
        $subcategory->update($request->all());
        return response()->json($subcategory);
    }

    public function destroy(Subcategory $subcategory)
    {
        $subcategory->delete();
        return response()->json(['message' => 'deleted successfully']);
    }
}