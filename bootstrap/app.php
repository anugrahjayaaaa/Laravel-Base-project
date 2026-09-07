<?php

use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\LogHttpErrors;
use App\Http\Middleware\RegistrationEnabled;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetApiLocale;
use App\Http\Middleware\SetLocale;
use App\Providers\EventServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->append(LogHttpErrors::class);
        // SetLocale MUST run after StartSession (web group), so the session is
        // readable. As global middleware it ran before session start -> always 'en'.
        $middleware->web(append: [SetLocale::class]);
        // PG webhook is called by the gateway (no CSRF token) — exclude from CSRF.
        $middleware->validateCsrfTokens(except: ['billing/webhook']);
        // API is stateless (Sanctum) — resolve locale from X-Locale / Accept-Language.
        $middleware->api(append: [SetApiLocale::class]);
        $middleware->alias([
            'feature' => EnsureFeatureEnabled::class,
            'registration.enabled' => RegistrationEnabled::class,
        ]);
    })
    ->withProviders([
        EventServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // ponytail: global unique-constraint safety net for SQLite/MySQL race conditions.
        // Not a substitute for per-field mapping; add per-field handling if 422 accuracy matters.
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($e instanceof \Illuminate\Database\QueryException && str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                return redirect()->back()->withErrors(['email' => __('messages.email_already_taken')])->withInput();
            }

            return null;
        });
    })->create();
