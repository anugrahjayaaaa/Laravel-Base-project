<?php

use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BillingAdminController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LogViewerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Health check (no auth)
Route::get('/up', fn () => response()->json(['status' => 'ok']));

// Email verification (web) — built-in signed URL, name required by VerifyEmail notification
Route::get('/email/verify/{id}/{hash}', [LoginController::class, 'verify'])
    ->name('verification.verify');

// Auth
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])
        ->middleware('throttle:10,15')->name('login.store');

    // Password reset (guest only). Token state lives in DB table `password_reset_tokens`.
    Route::get('forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:10,15')->name('password.email');
    Route::get('reset-password/{token}', [ForgotPasswordController::class, 'edit'])->name('password.reset');
    Route::post('reset-password', [ForgotPasswordController::class, 'update'])->name('password.store');

    // Self-service registration — only available when Setting::get('registration_enabled') is truthy.
    // When disabled the route never matches → 404 (fail-closed for security).
    Route::get('register', [RegisterController::class, 'show'])
        ->name('register')
        ->middleware('registration.enabled');
    Route::post('register', [RegisterController::class, 'store'])
        ->middleware(['throttle:10,15', 'registration.enabled'])
        ->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Stub routes for sidebar links (filled in by later phases: users/roles/permissions/audit/profile/sessions/api-tokens)
    Route::middleware('feature:users')->group(function () {
        Route::resource('users', UserController::class)->only(['index'])
            ->middleware('can:user.view');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create')->middleware('can:user.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store')->middleware('can:user.create');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit')->middleware('can:user.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update')->middleware('can:user.edit');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('can:user.delete');
        Route::post('/users/bulk', [UserController::class, 'bulk'])->name('users.bulk')->middleware(['can:user.delete']);
        Route::post('/users/{user}/restore', [UserController::class, 'restore'])->name('users.restore')->middleware(['can:user.restore']);
        Route::post('/users/{user}/force-delete', [UserController::class, 'forceDelete'])->name('users.forceDelete')->middleware(['can:user.force-delete']);
        Route::post('/users/{user}/lock', [UserController::class, 'lock'])->name('users.lock')->middleware(['can:user.lock']);
        Route::post('/users/{user}/unlock', [UserController::class, 'unlock'])->name('users.unlock')->middleware(['can:user.lock']);
        Route::post('/users/{user}/reset-password', [UserController::class, 'sendResetPassword'])->name('users.reset-password')->middleware(['can:user.edit']);
    });

    Route::middleware('feature:roles')->group(function () {
        Route::resource('roles', RoleController::class)->only(['index'])
            ->middleware('can:role.view');
        Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create')->middleware('can:role.create');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store')->middleware('can:role.create');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit')->middleware('can:role.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update')->middleware('can:role.edit');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('can:role.delete');
        Route::post('/roles/bulk', [RoleController::class, 'bulk'])->name('roles.bulk')->middleware(['can:role.delete']);
        Route::post('/roles/{role}/restore', [RoleController::class, 'restore'])->name('roles.restore')->middleware(['can:role.restore']);
        Route::post('/roles/{role}/force-delete', [RoleController::class, 'forceDelete'])->name('roles.forceDelete')->middleware(['can:role.force-delete']);
    });

    Route::middleware('feature:permissions')->group(function () {
        Route::resource('permissions', PermissionController::class)->only(['index'])
            ->middleware('can:permission.view');
        Route::get('permissions/create', [PermissionController::class, 'create'])->name('permissions.create')->middleware('can:permission.create');
        Route::post('permissions', [PermissionController::class, 'store'])->name('permissions.store')->middleware('can:permission.create');
        Route::get('permissions/{permission}/edit', [PermissionController::class, 'edit'])->name('permissions.edit')->middleware('can:permission.edit');
        Route::put('permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update')->middleware('can:permission.edit');
        Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy')->middleware('can:permission.delete');
        Route::post('/permissions/bulk', [PermissionController::class, 'bulk'])->name('permissions.bulk')->middleware(['can:permission.delete']);
        Route::post('/permissions/{permission}/restore', [PermissionController::class, 'restore'])->name('permissions.restore')->middleware(['can:permission.restore']);
        Route::post('/permissions/{permission}/force-delete', [PermissionController::class, 'forceDelete'])->name('permissions.forceDelete')->middleware(['can:permission.force-delete']);
    });

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/password', [ProfileController::class, 'changePassword'])->name('profile.password');

    Route::get('/sessions', [SessionController::class, 'index'])->name('sessions.index')->middleware(['feature:sessions', 'can:session.view']);
    Route::post('/sessions/logout-others', [SessionController::class, 'logoutOthers'])->name('sessions.logoutOthers')->middleware(['feature:sessions', 'can:session.revoke']);

    Route::get('/audit', [AuditController::class, 'index'])->name('audit.index')->middleware(['feature:audit', 'can:audit.view']);
    Route::get('/audit/export', [AuditController::class, 'export'])->name('audit.export')->middleware(['feature:audit', 'can:audit.view']);
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index')->middleware(['feature:audit', 'can:audit.view']);
    Route::get('/api-tokens', [ApiTokenController::class, 'index'])->name('api-tokens.index')->middleware(['feature:api-tokens', 'can:api-token.view']);
    Route::resource('api-tokens', ApiTokenController::class)
        ->only(['store', 'destroy'])
        ->middleware(['feature:api-tokens', 'can:api-token.create']);

    // Web log viewer
    Route::get('/logs', [LogViewerController::class, 'index'])
        ->name('logs.index')->middleware(['feature:logs', 'can:logs.view']);

    // Feature flags management (self-gated: feature.manage permission)
    Route::get('/features', [FeatureController::class, 'index'])->name('features.index')->middleware('can:feature.manage');
    Route::post('/features/{slug}/toggle', [FeatureController::class, 'toggle'])->name('features.toggle')->middleware('can:feature.manage');

    // Plan management (full CRUD, custom slug/price/limits/features — doc §9b)
    Route::resource('plans', PlanController::class)->middleware(['feature:plans', 'can:feature.manage']);

    // Billing: user portal + checkout (dummy mode completes at once)
    Route::prefix('billing')->middleware('feature:billing')->group(function () {
        Route::get('/', [BillingController::class, 'index'])->name('billing.index');
        Route::post('/checkout', [BillingController::class, 'checkout'])
            ->name('billing.checkout')->middleware('throttle:10,1');
        Route::post('/cancel', [BillingController::class, 'cancel'])
            ->name('billing.cancel')->middleware(['feature:billing', 'can:billing.cancel']);
        Route::get('/invoice/{payment}', [BillingController::class, 'invoice'])
            ->name('billing.invoice');
    });

    // Billing admin: KPIs + analytics (separate popular page, gated by billing.view)
    Route::prefix('admin/billing')->middleware(['feature:billing', 'can:billing.view'])->group(function () {
        Route::get('/', [BillingAdminController::class, 'index'])->name('admin.billing.index');
    });

    Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

    // Translations management (under Settings, gated by RBAC + feature flag)
    Route::prefix('settings')->middleware(['feature:translations', 'can:translation.view'])->group(function () {
        Route::get('/translations', [TranslationController::class, 'index'])->name('translations.index');
        Route::get('/translations/create', [TranslationController::class, 'create'])->name('translations.create')->middleware('can:translation.create');
        Route::post('/translations', [TranslationController::class, 'store'])->name('translations.store')->middleware('can:translation.create');
        Route::get('/translations/{languageLine}/edit', [TranslationController::class, 'edit'])->name('translations.edit')->middleware('can:translation.edit');
        Route::put('/translations/{languageLine}', [TranslationController::class, 'update'])->name('translations.update')->middleware('can:translation.edit');
    });

    // System settings (default locale + registration toggle) — gated by feature.manage
    Route::prefix('settings')->name('settings.')->middleware('can:feature.manage')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('system');
        Route::post('/', [SettingsController::class, 'update'])->name('system.update');
    });

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::post('/email/verify/resend', [LoginController::class, 'resendVerification'])->name('verification.resend');
});

// PG webhook — outside auth (the gateway calls this, no session). CSRF excluded in bootstrap/app.php.
Route::post('/billing/webhook', [BillingController::class, 'webhook'])->name('billing.webhook');

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : view('auth.login'));
