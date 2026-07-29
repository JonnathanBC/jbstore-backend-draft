<?php

use App\Modules\Products\Http\Controllers\PublicProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/public')->group(function () {
    Route::resource('/products', PublicProductController::class);
});
