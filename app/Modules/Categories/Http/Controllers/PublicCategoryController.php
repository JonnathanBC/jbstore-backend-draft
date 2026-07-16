<?php

namespace App\Modules\Categories\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Categories\Models\Category;
use Illuminate\Http\Request;

class PublicCategoryController extends Controller
{
    public function __invoke(Request $request)
    {
        $query = Category::with('family', 'subcategories');

        if ($request->filled('family_id')) {
            $query->where('family_id', $request->input('family_id'));
        }

        return $this->paginated(
            $query,
            $request,
            ['updated_at'],
        );
    }
}
