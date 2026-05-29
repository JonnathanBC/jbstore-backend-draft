<?php

namespace App\Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Products\Models\Feature;

class FeatureController extends Controller
{
    public function index(Request $request)
    {
        $allowedSortable = ['updated_at'];
        $query = Feature::query();

        if ($request->filled('option_id')) {
            $query->where('option_id', $request->input('option_id'));
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
            'value' => 'required',
            'description' => 'required',
            'option_id' => 'required|exists:options,id'
        ]);

        $data = Feature::create($request->all());

        return response()->json($data, 201);
    }

    public function destroy(Feature $feature)
    {
        $feature->delete();
        return response()->json(null, 204);
    }
}
