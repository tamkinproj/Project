<?php

use App\Modules\Moderation\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active', 'throttle:reports'])->group(function () {
    Route::post('reports', [ReportController::class, 'store']);
});
