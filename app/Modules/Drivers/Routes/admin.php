<?php

use App\Modules\Drivers\Http\Controllers\DriverController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'can:admin'])
    ->prefix('api/admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('drivers', DriverController::class);
    });
