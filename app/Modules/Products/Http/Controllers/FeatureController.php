<?php

namespace App\Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Products\Models\Feature;

class FeatureController extends Controller
{
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

    public function destroy($id)
    {
        $feature = Feature::findOrFail($id);
        $feature->delete();

        return response()->json(null, 204);
    }
}
