<?php

namespace App\Modules\Categories\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Modules\Categories\Models\Family;
use App\Modules\Products\Models\Feature;
use App\Modules\Products\Models\Product;
use Illuminate\Http\Request;

class PublicFamilyProductController extends Controller
{
    public function __invoke(Request $request, Family $family)
    {
        $query = Product::query()
            ->whereHas('subcategory.category', function ($query) use ($family) {
                $query->where('family_id', $family->id);
            })
            ->when($request->input('orderBy') === 'relevant', function ($query) {
                $query->orderBy('created_at', 'desc');
            })->when($request->input('orderBy') === 'major_to_minor', function ($query) {
                $query->orderBy('price', 'desc');
            })->when($request->input('orderBy') === 'minor_to_major', function ($query) {
                $query->orderBy('price', 'asc');
            });

        $featureIds = array_filter(
            array_map('intval', (array) $request->input('features', []))
        );

        if ($featureIds) {
            // Features de la misma opción se combinan con OR (Color: rojo o azul),
            // opciones distintas con AND (Color rojo Y Talle M). Todo tiene que
            // cumplirse sobre la MISMA variante: un producto con rojo-L y azul-M
            // no matchea rojo-M.
            $grouped = Feature::whereIn('id', $featureIds)
                ->get()
                ->groupBy('option_id')
                ->map(fn ($features) => $features->pluck('id')->all());

            $query->whereHas('variants', function ($variantQuery) use ($grouped) {
                foreach ($grouped as $ids) {
                    $variantQuery->whereHas('features', function ($featureQuery) use ($ids) {
                        $featureQuery->whereIn('features.id', $ids);
                    });
                }
            });
        }

        return $this->paginated(
            $query,
            $request,
            ['updated_at', 'price'],
        );
    }
}
