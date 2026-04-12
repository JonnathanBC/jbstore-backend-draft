<?php

use App\Modules\Categories\Http\Controllers\FamilyController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::resource('families', FamilyController::class);
});
