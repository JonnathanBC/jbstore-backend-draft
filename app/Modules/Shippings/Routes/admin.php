<?php

use App\Modules\Shippings\Http\Controllers\ShippingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'can:manage.shippings'])
    ->prefix('api/admin')
    ->name('admin.')
    ->group(function () {
        Route::post('/orders/{id}/shipping', [ShippingController::class, 'store']);

        Route::get('/shippings', [ShippingController::class, 'index']);
        Route::patch('/shippings/{id}/status', [ShippingController::class, 'updateStatus']);
    });
