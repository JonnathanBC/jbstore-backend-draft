<?php

namespace App\Modules\Categories\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Categories\Models\Family;

class FamilyController extends Controller
{
    public function index()
    {
        $family = Family::paginate();

        return response()->json($family);
    }

    public function create()
    {
        return response()->json(['message' => 'create']);
    }

    public function store(Request $request)
    {
        $data = Family::create($request->all());

        return response()->json($data, 201);
    }

    public function show(Family $family)
    {
        return response()->json($family);
    }

    public function edit()
    {
        return response()->json(['message' => 'edit']);
    }

    public function update(Request $request, Family $family)
    {
        $family->update($request->all());

        return response()->json($family);
    }

    public function destroy(Family $family)
    {
        $family->delete();

        return response()->json(['message' => 'deleted successfully']);
    }
}
