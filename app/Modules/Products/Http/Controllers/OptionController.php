<?php

namespace App\Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Products\Models\Option;
use Illuminate\Http\Request;

class OptionController extends Controller
{
    public function index(Request $request)
    {
        $allowedSortable = ['updated_at'];
        $query = Option::with('features');

        return $this->paginated(
            $query,
            $request,
            $allowedSortable,
        );
    }

    public function store(Request $request)
    {
        $rules = [
            'name' => 'required',
            'type' => 'required|in:1,2',
            'features' => 'required|array|min:1',
        ];

        foreach ($request->input('features') as $index => $feature) {

            switch ($request->input('type')) {
                case 1:
                    // type 1 texto
                    $rules["features.$index.value"] = 'required';
                    break;

                default:
                    // type 2 color
                    $rules["features.$index.value"] = 'required|regex:/^#[a-f0-9]{6}$/i';
                    break;
            }


            $rules["features.$index.description"] = 'required|max:255';
        }

        $request->validate($rules);

        $option = Option::create([
            'name' => $request->input('name'),
            'type' => $request->input('type'),
        ]);

        foreach ($request->input('features') as $feature) {
            $option->features()->create([
                'value' => $feature['value'],
                'description' => $feature['description'],
            ]);
        }

        return response()->json($option, 201);
    }
}
