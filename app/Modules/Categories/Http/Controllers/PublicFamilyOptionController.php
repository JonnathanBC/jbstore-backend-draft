<?php

namespace App\Modules\Categories\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Modules\Categories\Models\Family;
use App\Modules\Products\Models\Option;

class PublicFamilyOptionController extends Controller
{
    public function __invoke(Family $family)
    {
        $options = Option::whereHas('products.subcategory.category', function ($query) use ($family) {
            $query->where('family_id', $family->id);
        })->with([
            'features' => function ($query) use ($family) {
                $query->whereHas('variants.product.subcategory.category', function ($query) use ($family) {
                    $query->where('family_id', $family->id);
                });
            },
        ])
        ->get();

        return response()->json([
            'data' => $options,
        ]);
    }
}
