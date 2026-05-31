<?php

namespace App\Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Products\Models\OptionProduct;
use Illuminate\Http\Request;

class OptionProductController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'product_id'              => 'required|exists:products,id',
            'option_id'               => 'required|exists:options,id',
            'features'                => 'required|array|min:1',
            'features.*.id'          => 'required',
            'features.*.value'       => 'required',
            'features.*.description' => 'required',
        ]);

        $optionProduct = OptionProduct::create($request->only('product_id', 'option_id', 'features'));

        return response()->json($optionProduct, 201);
    }
}
