<?php

use App\Modules\Users\Http\Controllers\CoverController;
use App\Modules\Users\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('api/admin')
    ->name('admin.')
    ->group(function () {
        Route::middleware('can:manage.covers')->group(function () {
            Route::post('covers/reorder', [CoverController::class, 'reorder']);
            Route::apiResource('covers', CoverController::class);
        });

        Route::middleware('can:manage.users')
            ->get('users', [UserController::class, 'index'])
            ->name('users.index');

        Route::middleware('can:manage.users')
            ->get('users/list', [UserController::class, 'listing'])
            ->name('users.list');
    });
