<?php

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Cover;

class PublicCoverController extends Controller
{
    public function __invoke()
    {
        return response()->json([
            'data' => Cover::current()->orderBy('order')->get(),
        ]);
    }
}
