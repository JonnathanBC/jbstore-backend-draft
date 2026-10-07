<?php

use App\Modules\Orders\Http\Controllers\OrderController;
use App\Modules\Orders\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'can:admin'])
    ->prefix('api/admin')
    ->name('admin.')
    ->group(function () {
        Route::apiResource('orders', OrderController::class);

        Route::get('/orders/{order}/ticket/download', [OrderController::class, 'downloadOrderTicket']);
        Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    });
