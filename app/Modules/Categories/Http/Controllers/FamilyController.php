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
        $request->validate([
            'name' => 'required',
        ]);

        $data = Family::create($request->all());
        return response()->json($data, 201);
    }

    public function show(Family $family)
    {
        return response()->json($family);
    }

    public function update(Request $request, Family $family)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $family->update($request->all());

        return response()->json($family);
    }

    public function destroy(Family $family)
    {
        if ($family->categories()->count() > 0) {
            return response()->json(['message' => 'No se puede eliminar una familia con categorías'], 422);
        }

        $family->delete();

        return response()->json(['message' => 'deleted successfully']);
    }
}
