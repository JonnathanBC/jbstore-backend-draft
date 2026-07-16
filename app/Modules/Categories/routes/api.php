<?php

use App\Modules\Categories\Http\Controllers\PublicCategoryController;
use App\Modules\Categories\Http\Controllers\PublicFamilyController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/public')->group(function () {
    Route::get('/families', PublicFamilyController::class);
    Route::get('/categories', PublicCategoryController::class);
});
