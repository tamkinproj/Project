<?php

use App\Modules\Admin\Http\Controllers\AuditLogController;
use App\Modules\Admin\Http\Controllers\OverviewController;
use App\Modules\Admin\Http\Controllers\RoleController;
use App\Modules\Admin\Http\Controllers\SystemHealthController;
use App\Modules\Admin\Http\Controllers\UserAdminController;
use App\Modules\Card\Http\Controllers\Admin\CardAdminController;
use App\Modules\Moderation\Http\Controllers\Admin\ContentModerationController;
use App\Modules\Moderation\Http\Controllers\Admin\ReportAdminController;
use Illuminate\Support\Facades\Route;

// Every route checks its own permission server-side; the admin UI hiding a link is never the control.
Route::middleware(['auth:sanctum', 'active'])->prefix('admin')->group(function () {
    Route::get('overview', OverviewController::class);
    Route::get('system/health', SystemHealthController::class)->middleware('can:system.health');

    Route::middleware('can:users.view')->group(function () {
        Route::get('users', [UserAdminController::class, 'index']);
        Route::get('users/{user}', [UserAdminController::class, 'show']);
    });
    Route::middleware('can:users.suspend')->group(function () {
        Route::post('users/{user}/suspend', [UserAdminController::class, 'suspend']);
        Route::post('users/{user}/unsuspend', [UserAdminController::class, 'unsuspend']);
    });
    Route::middleware('can:roles.manage')->group(function () {
        Route::get('roles', [RoleController::class, 'index']);
        Route::post('users/{user}/roles', [UserAdminController::class, 'grantRole']);
        Route::delete('users/{user}/roles/{role}', [UserAdminController::class, 'revokeRole'])->where('role', '[a-z_]+');
    });

    Route::middleware('can:reports.review')->group(function () {
        Route::get('reports', [ReportAdminController::class, 'index']);
        Route::get('reports/{report}', [ReportAdminController::class, 'show']);
        Route::post('reports/{report}/resolve', [ReportAdminController::class, 'resolve']);
    });
    Route::middleware('can:content.moderate')->group(function () {
        Route::post('posts/{post}/hide', [ContentModerationController::class, 'hidePost']);
        Route::post('posts/{post}/restore', [ContentModerationController::class, 'restorePost']);
        Route::post('comments/{comment}/hide', [ContentModerationController::class, 'hideComment']);
        Route::post('comments/{comment}/restore', [ContentModerationController::class, 'restoreComment']);
        Route::get('moderation/actions', [ContentModerationController::class, 'history']);
    });

    Route::middleware('can:cards.manage')->group(function () {
        Route::get('cards', [CardAdminController::class, 'index']);
        Route::get('cards/{card}', [CardAdminController::class, 'show']);
        Route::post('cards/{card}/revoke', [CardAdminController::class, 'revoke']);
    });
    Route::post('cards', [CardAdminController::class, 'store'])->middleware('can:cards.issue');

    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('can:audit.view');
});
