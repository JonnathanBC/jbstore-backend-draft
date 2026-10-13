<?php

use App\Modules\Orders\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'can:manage.orders'])
    ->prefix('api/admin')
    ->name('admin.')
    ->group(function () {
        Route::apiResource('orders', OrderController::class);

        Route::get('/orders/{id}/ticket/download', [OrderController::class, 'downloadOrderTicket']);
        Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    });
