<?php

use App\Modules\Categories\Http\Controllers\PublicCategoryController;
use App\Modules\Categories\Http\Controllers\PublicFamilyController;
use App\Modules\Categories\Http\Controllers\PublicFamilyOptionController;
use App\Modules\Categories\Http\Controllers\PublicFamilyProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/public')->group(function () {
    Route::get('/families/{family}/options', PublicFamilyOptionController::class);
    Route::get('/families/{family}/products', PublicFamilyProductController::class);
    Route::apiResource('/families', PublicFamilyController::class)->only(['index', 'show']);
    Route::get('/categories', PublicCategoryController::class);
});
