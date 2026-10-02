<?php

use App\Modules\Payments\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/payments')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/token', [PaymentController::class, 'generateToken']);
        Route::post('/session', [PaymentController::class, 'generateTokenSession']);
        Route::post('/capture', [PaymentController::class, 'capturePayment']);
    });
