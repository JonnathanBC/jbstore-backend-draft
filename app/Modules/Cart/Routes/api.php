<?php

use App\Modules\Cart\Http\Controllers\CartController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/cart')->middleware('auth:sanctum')->group(function () {
    Route::get('/items', [CartController::class, 'index']);
    Route::post('/items', [CartController::class, 'store']);
    Route::patch('/items/{rowId}', [CartController::class, 'update']);
    Route::post('/merge', [CartController::class, 'merge']);
    Route::delete('/items', [CartController::class, 'clear']);
    Route::delete('/items/{rowId}', [CartController::class, 'destroy']);
});
