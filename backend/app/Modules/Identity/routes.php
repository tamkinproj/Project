<?php

use App\Modules\Identity\Http\Controllers\AccountController;
use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\EmailVerificationController;
use App\Modules\Identity\Http\Controllers\PasswordResetController;
use App\Modules\Identity\Http\Controllers\PrivacyController;
use App\Modules\Identity\Http\Controllers\PrivateProfileController;
use App\Modules\Identity\Http\Controllers\ProfileSettingsController;
use App\Modules\Identity\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:password-reset');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:password-reset');
    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        // The signature is the protection; a tight per-IP limit would only hurt shared networks.
        ->middleware('signed:relative')
        ->name('verification.verify');
});

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:verification');

    Route::get('me', [AccountController::class, 'show']);
    Route::put('me/password', [AccountController::class, 'changePassword'])->middleware('throttle:sensitive');
    Route::post('me/deactivate', [AccountController::class, 'deactivate'])->middleware('throttle:sensitive');
    Route::delete('me', [AccountController::class, 'destroy'])->middleware('throttle:sensitive');

    Route::patch('me/profile', [ProfileSettingsController::class, 'update']);
    Route::post('me/avatar', [ProfileSettingsController::class, 'updateAvatar'])->middleware('throttle:content');
    Route::delete('me/avatar', [ProfileSettingsController::class, 'deleteAvatar']);

    Route::get('me/private-profile', [PrivateProfileController::class, 'show']);
    Route::patch('me/private-profile', [PrivateProfileController::class, 'update'])->middleware('throttle:sensitive');

    Route::get('me/privacy', [PrivacyController::class, 'show']);
    Route::patch('me/privacy', [PrivacyController::class, 'update']);

    Route::get('me/sessions', [SessionController::class, 'index']);
    Route::delete('me/sessions', [SessionController::class, 'destroyOthers']);
    Route::delete('me/sessions/{session}', [SessionController::class, 'destroy']);
});
