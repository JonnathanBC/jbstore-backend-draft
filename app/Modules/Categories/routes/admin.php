<?php

use App\Modules\Categories\Http\Controllers\CategoryController;
use App\Modules\Categories\Http\Controllers\FamilyController;
use App\Modules\Categories\Http\Controllers\SubcategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('api/admin')
    ->name('admin.')
    ->group(function () {
        Route::middleware('can:manage.families')
            ->apiResource('families', FamilyController::class);

        Route::middleware('can:manage.categories')
            ->apiResource('categories', CategoryController::class);

        Route::middleware('can:manage.subcategories')
            ->apiResource('subcategories', SubcategoryController::class);
    });
