<?php

use Illuminate\Support\Facades\Route;

Route::prefix('api/shipping')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/');
    });
