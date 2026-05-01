<?php

use App\Modules\Products\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'can:admin'])
    ->prefix('api')
    ->name('admin.')
    ->group(function () {
        Route::apiResource('products', ProductController::class);
    });
