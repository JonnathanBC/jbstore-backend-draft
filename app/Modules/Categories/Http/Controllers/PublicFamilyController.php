<?php

namespace App\Modules\Categories\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Modules\Categories\Models\Family;

class PublicFamilyController extends Controller
{
    public function __invoke(Request $request)
    {
        $allowedSortable = ['updated_at'];

        return $this->paginated(
            Family::query(),
            $request,
            $allowedSortable,
        );
    }
}
