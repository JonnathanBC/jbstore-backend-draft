<?php

namespace App\Modules\Products\Listeners;

use App\Modules\Categories\Events\SubcategoryDeleting;
use App\Modules\Products\Models\Product;
use Illuminate\Validation\ValidationException;

class CheckProductsBeforeSubcategoryDeletedListener
{
    public function handle(SubcategoryDeleting $event)
    {
        // El evento trae la instancia de la subcategoría
        $subcategory = $event->subcategory;

        // Consultamos al modelo de Productos (que pertenece a este módulo)
        $hasProducts = Product::where('subcategory_id', $subcategory->id)->exists();

        if ($hasProducts) {
            throw ValidationException::withMessages([
                'subcategory' => 'No se puede eliminar esta subcategoría tiene productos asociados.'
            ]);
        }
    }
}
