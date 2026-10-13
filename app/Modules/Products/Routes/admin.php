<?php

use App\Modules\Products\Http\Controllers\FeatureController;
use App\Modules\Products\Http\Controllers\OptionController;
use App\Modules\Products\Http\Controllers\OptionProductController;
use App\Modules\Products\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('api/admin')
    ->name('admin.')
    ->group(function () {
        Route::middleware('can:manage.products')->group(function () {
            Route::apiResource('products', ProductController::class);
            Route::get('products/{product}/variants/{variant}', [ProductController::class, 'variants'])
                ->name('products.variants')
                ->scopeBindings();
            Route::put('products/{product}/variants/{variant}', [ProductController::class, 'variantsUpdate'])
                ->name('products.variantsUpdate')
                ->scopeBindings();

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

        Route::middleware('can:manage.options')->group(function () {
            Route::resource('options', OptionController::class);
            Route::resource('features', FeatureController::class);
        });
    });
