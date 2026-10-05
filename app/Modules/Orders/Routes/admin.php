<?php

use App\Modules\Orders\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'can:admin'])
    ->prefix('api/admin')
    ->name('admin.')
    ->group(function () {
        Route::apiResource('orders', OrderController::class);
    });
