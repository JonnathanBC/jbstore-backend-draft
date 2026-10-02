<?php

use Illuminate\Support\Facades\Route;

Route::prefix('api/orders')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/');
    });
