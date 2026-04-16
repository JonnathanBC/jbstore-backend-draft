<?php

use App\Modules\Categories\Http\Controllers\CategoryController;
use App\Modules\Categories\Http\Controllers\FamilyController;
use App\Modules\Categories\Http\Controllers\SubcategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'can:admin'])
    ->prefix('api')
    ->name('admin.')
    ->group(function () {
        Route::apiResource('families', FamilyController::class);
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('subcategories', SubcategoryController::class);
    });
