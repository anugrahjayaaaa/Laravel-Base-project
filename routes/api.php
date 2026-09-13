<?php

use App\Http\Controllers\Api\ApiTokenApiController;
use App\Http\Controllers\Api\AuditApiController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\FeatureApiController;
use App\Http\Controllers\Api\NotificationApiController;
use App\Http\Controllers\Api\PermissionApiController;
use App\Http\Controllers\Api\ProfileApiController;
use App\Http\Controllers\Api\RoleApiController;
use App\Http\Controllers\Api\SessionApiController;
use App\Http\Controllers\Api\UserApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1) — Sanctum Bearer token auth
|--------------------------------------------------------------------------
| Header: Authorization: Bearer <token>
| All responses are JSON. Errors follow { "message": ..., "errors": {...} }.
*/

Route::prefix('v1')->group(function () {
    // Public
    Route::post('login', [AuthApiController::class, 'login'])->middleware('throttle:10,15');
    Route::post('forgot-password', [AuthApiController::class, 'forgotPassword'])->middleware('throttle:10,15');
    Route::post('reset-password', [AuthApiController::class, 'resetPassword'])->middleware('throttle:10,15');
    Route::get('email/verify', [AuthApiController::class, 'verifyEmail'])->name('api.verification.verify');

    // Authenticated
    Route::middleware('auth:sanctum')->name('api.')->group(function () {
        Route::get('me', [AuthApiController::class, 'me']);
        Route::post('logout', [AuthApiController::class, 'logout']);
        Route::post('password/change', [AuthApiController::class, 'changePassword']);
        Route::post('email/verify/resend', [AuthApiController::class, 'resendVerification']);

        // Profile + own sessions/tokens
        Route::get('profile', [ProfileApiController::class, 'show']);
        Route::put('profile', [ProfileApiController::class, 'update']);
        Route::post('profile/password', [ProfileApiController::class, 'changePassword']);
        Route::get('sessions', [SessionApiController::class, 'index']);
        Route::post('sessions/logout-others', [SessionApiController::class, 'logoutOthers']);
        Route::get('api-tokens', [ApiTokenApiController::class, 'index']);
        Route::post('api-tokens', [ApiTokenApiController::class, 'store']);
        Route::delete('api-tokens/{token}', [ApiTokenApiController::class, 'destroy']);

        // Notifications
        Route::get('notifications', [NotificationApiController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationApiController::class, 'unreadCount']);
        Route::post('notifications/mark-all-read', [NotificationApiController::class, 'markAllRead']);

        // Features (feature.manage)
        Route::get('features', [FeatureApiController::class, 'index']);
        Route::post('features/{slug}/toggle', [FeatureApiController::class, 'toggle'])->middleware('can:feature.manage');

        // Audit (audit.view)
        Route::get('audit', [AuditApiController::class, 'index'])->middleware('can:audit.view');
        Route::get('audit/actions', [AuditApiController::class, 'actions'])->middleware('can:audit.view');

        // Users (user.*)
        Route::apiResource('users', UserApiController::class)->middleware('can:user.view');
        Route::post('users/{user}/lock', [UserApiController::class, 'lock'])->middleware('can:user.lock');
        Route::post('users/{user}/unlock', [UserApiController::class, 'unlock'])->middleware('can:user.lock');
        Route::post('users/{user}/reset-password', [UserApiController::class, 'sendResetPassword'])->middleware('can:user.update');
        Route::post('users/{user}/restore', [UserApiController::class, 'restore'])->middleware('can:user.manage');
        Route::delete('users/{user}/force-delete', [UserApiController::class, 'forceDelete'])->middleware('can:user.manage');

        // Roles (role.*)
        Route::apiResource('roles', RoleApiController::class)->middleware('can:role.view');
        Route::post('roles/{id}/restore', [RoleApiController::class, 'restore'])->middleware('can:role.manage');
        Route::delete('roles/{id}/force-delete', [RoleApiController::class, 'forceDelete'])->middleware('can:role.manage');

        // Permissions (permission.*)
        Route::apiResource('permissions', PermissionApiController::class)->middleware('can:permission.view');
        Route::post('permissions/{permission}/restore', [PermissionApiController::class, 'restore'])->middleware('can:permission.manage');
        Route::delete('permissions/{permission}/force-delete', [PermissionApiController::class, 'forceDelete'])->middleware('can:permission.manage');
    });
});
