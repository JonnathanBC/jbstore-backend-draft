<?php

use App\Modules\Shippings\Http\Controllers\ShippingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'can:admin'])
    ->prefix('api/admin')
    ->name('admin.')
    ->group(function () {
        Route::post('/orders/{id}/shipping', [ShippingController::class, 'store']);
    });
