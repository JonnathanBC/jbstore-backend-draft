<?php

use App\Modules\Addresses\Http\Controllers\AddressController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/addresses')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/', [AddressController::class, 'index']);
        Route::post('/', [AddressController::class, 'store']);
    });
