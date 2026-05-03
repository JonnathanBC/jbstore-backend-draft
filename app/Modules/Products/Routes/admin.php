<?php

use App\Modules\Products\Http\Controllers\OptionController;
use App\Modules\Products\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'can:admin'])
    ->prefix('api')
    ->name('admin.')
    ->group(function () {
        // Products
        Route::apiResource('products', ProductController::class);

        // Options
        Route::get('options', [OptionController::class, 'index'])->name('options.index');
    });
