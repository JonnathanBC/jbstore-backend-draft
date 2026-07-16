<?php

use App\Modules\Users\Http\Controllers\CoverController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'can:admin'])
    ->prefix('api/admin')
    ->name('admin.')
    ->group(function () {
        Route::post('covers/reorder', [CoverController::class, 'reorder']);
        Route::apiResource('covers', CoverController::class);
    });
