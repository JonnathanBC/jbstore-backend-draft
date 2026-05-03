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
}
