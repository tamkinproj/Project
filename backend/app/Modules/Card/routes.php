<?php

use App\Modules\Card\Http\Controllers\MyCardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->prefix('me/cards')->group(function () {
    Route::get('/', [MyCardController::class, 'index']);
    Route::post('link', [MyCardController::class, 'link'])->middleware(['verified', 'throttle:card-link']);
    Route::get('{card}/events', [MyCardController::class, 'events']);

    Route::middleware('throttle:sensitive')->group(function () {
        Route::post('{card}/freeze', [MyCardController::class, 'freeze']);
        Route::post('{card}/unfreeze', [MyCardController::class, 'unfreeze']);
        Route::post('{card}/report-lost', [MyCardController::class, 'reportLost']);
        Route::post('{card}/request-replacement', [MyCardController::class, 'requestReplacement']);
    });
});
