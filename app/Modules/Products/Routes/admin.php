<?php

use App\Modules\Products\Http\Controllers\FeatureController;
use App\Modules\Products\Http\Controllers\OptionController;
use App\Modules\Products\Http\Controllers\OptionProductController;
use App\Modules\Products\Http\Controllers\ProductController;

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'can:admin'])
    ->prefix('api')
    ->name('admin.')
    ->group(function () {
        // Products
        Route::apiResource('products', ProductController::class);

        // Options
        Route::resource('options', OptionController::class);

        // Features
        Route::resource('features', FeatureController::class);

        // Option Products
        Route::post(
            'option-products/remove-features',
            [OptionProductController::class, 'removeFeature']
        );
        Route::post(
            'option-products/remove-option',
            [OptionProductController::class, 'removeOption']
        );
        Route::post('options-product', [OptionProductController::class, 'store']);
    });
