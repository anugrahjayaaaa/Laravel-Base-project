<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\Auditable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    use Auditable;

    public function show()
    {
        return view('auth.login');
    }

    /**
     * Authenticate a user via email or username and start a session.
     *
     * @param  LoginRequest  $request  Validated: identifier (email|username), password, optional remember (bool)
     * @return RedirectResponse Redirect to intended page (dashboard) on success,
     *                          or back with 'identifier' error on failure / lockout.
     *
     * @throws ValidationException On bad credentials or too many attempts (429-style lockout)
     *
     * @details
     * - Login field resolved: email if value looks like an email, else username (phone removed).
     * - Rate limiter key: "login:{ip}:{identifier}" stored in CACHE (driver=config('cache.default'),
     *   currently 'database' -> table `cache`, key column). Max 5 hits; on fail adds a 900s (15m) window.
     * - On success: clears the rate-limiter key, regenerates SESSION (driver='database' -> table `sessions`).
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $identifier = (string) $request->input('identifier');
        $throttleKey = 'login:'.$request->ip().':'.strtolower($identifier);
        $userKey = 'login:user:'.strtolower($identifier); // ponytail: account-centric; survives IP rotation

        if (RateLimiter::tooManyAttempts($userKey, 5) || RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = max(RateLimiter::availableIn($userKey), RateLimiter::availableIn($throttleKey));
            throw ValidationException::withMessages([
                'identifier' => __('messages.too_many_attempts', ['seconds' => $seconds]),
            ]);
        }

        // Resolve login field: email OR username
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($field, $identifier)->first();

        // Account-level lockout (survives IP rotation; auto-unlocks when locked_until passes)
        if ($user && $user->isLocked()) {
            $message = $user->isPermanentlyLocked()
                ? __('messages.account_locked_permanent')
                : __('messages.account_locked_retry', ['seconds' => $user->locked_until->diffInSeconds(now())]);
            throw ValidationException::withMessages([
                'identifier' => $message,
            ]);
        }

        if (! $user || ! Auth::guard('web')->attempt([$field => $identifier, 'password' => $request->password], $request->boolean('remember'))) {
            // Failed attempt -> increment lock window in CACHE (table `cache`), 900s
            RateLimiter::hit($userKey, 900);
            RateLimiter::hit($throttleKey, 900);

            // On the 5th failed attempt, lock the account for 15 minutes (DB-persisted)
            if (RateLimiter::attempts($userKey) >= 5 && $user) {
                $user->update(['locked_until' => now()->addMinutes(15)]);

                $this->audit($user, 'account_locked_auto', $user);
            }

            throw ValidationException::withMessages([
                'identifier' => __('messages.invalid_credentials'),
            ]);
        }

        // ponytail: MustVerifyEmail blocks protected routes, but does NOT block login itself —
        // reject login outright for unverified emails (doc auth.md §Email verification).
        if (! $user->hasVerifiedEmail()) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages([
                'email' => __('messages.email_not_verified'),
            ]);
        }

        // Login event is dispatched by Auth::attempt() -> LogAuthentication listener.
        // No manual event dispatch here; double-fire would duplicate the login_success audit row.
        RateLimiter::clear($userKey);
        RateLimiter::clear($throttleKey);
        // Clear any expired lock marker on successful login
        if ($user->locked_until !== null) {
            $user->update(['locked_until' => null]);
        }
        // ponytail: record last successful login for security visibility
        $user->update(['last_login_at' => now(), 'last_login_ip' => $request->ip()]);
        // Regenerate SESSION (driver='database' -> table `sessions`) to prevent fixation
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Verify email via the built-in signed URL (route name `verification.verify`).
     * Uses URL::hasValidSignature so the `signature` + `expires` params are honored.
     */
    public function verify(Request $request, int $id): RedirectResponse
    {
        if (! URL::hasValidSignature($request)) {
            abort(403, __('messages.invalid_verification_link'));
        }

        $user = User::findOrFail($id);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            $this->audit($user, 'email_verified', $user);
        }

        return redirect()->route('login')
            ->with('status', __('messages.email_verified'));
    }

    /**
     * Resend the verification email (authenticated user only).
     */
    public function resendVerification(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('profile.show')
                ->with('status', __('messages.email_already_verified'));
        }

        $user->sendEmailVerificationNotification();

        return redirect()->route('profile.show')
            ->with('status', __('messages.verification_link_sent'));
    }

    /**
     * Log the current user out and destroy the session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
