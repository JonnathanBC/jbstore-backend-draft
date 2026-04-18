<?php

namespace App\Modules\Categories\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Modules\Categories\Models\Family;

class FamilyController extends Controller
{
    public function index(Request $request)
    {
        $allowedSortable = ['updated_at'];

        return $this->paginated(
            Family::query(),
            $request,
            $allowedSortable,
        );
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
