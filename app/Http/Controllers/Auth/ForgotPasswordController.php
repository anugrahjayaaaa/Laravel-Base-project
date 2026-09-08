<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\Auditable;
use App\Http\Requests\Auth\PasswordEmailRequest;
use App\Http\Requests\Auth\PasswordResetRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    use Auditable;
    /**
     * Show the "forgot password" form.
     *
     * @return View auth.forgot-password
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Send a password reset link to the given email.
     *
     * @param  PasswordEmailRequest  $request  Validated: email (valid email)
     * @return RedirectResponse Back with 'status' on success, or back with 'email' error.
     *
     * @throws ValidationException If the email is not found / broker error
     *
     * @details Uses the 'users' password broker (config('auth.passwords.users')).
     * The reset token is stored in DB table `password_reset_tokens` (keyed by email).
     * The email itself is sent via the default ResetPassword notification
     * (requires MAIL_* config to actually deliver).
     */
    public function store(PasswordEmailRequest $request): RedirectResponse
    {
        $status = Password::broker('users')->sendResetLink(
            $request->validated()
        );

        // ponytail: broker emits stdlib 'passwords.*' status keys (e.g.
        // 'passwords.user', 'passwords.throttled'). Map every one to a
        // project message key (lang/{en,id}/messages.php) so no raw key
        // ever reaches the view — there is a single source of truth.
        if ($status === Password::RESET_LINK_SENT) {
            $this->auditAction('password_reset_request', $request->user(), [
                'email' => $request->email,
            ]);

            return back()->with('status', __('messages.reset_link_sent_simple'));
        }

        throw ValidationException::withMessages([
            'email' => $this->statusMessage($status),
        ]);
    }

    /**
     * Show the password reset form.
     *
     * @param  string  $token  Reset token from the email link
     * @return View auth.reset-password with $token and $email (from query)
     */
    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->email ?? '']);
    }

    /**
     * Reset the user's password.
     *
     * @param  PasswordResetRequest  $request  Validated: token, email, password (min:8), password_confirmation
     * @return RedirectResponse Redirect to login with 'status' on success, or back with 'email' error.
     *
     * @throws ValidationException On invalid/expired token or mismatch
     *
     * @details Verifies token against DB table `password_reset_tokens` via the 'users' broker,
     * then updates the user's password and clears the token row.
     */
    public function update(PasswordResetRequest $request): RedirectResponse
    {
        $status = Password::broker('users')->reset(
            $request->validated(),
            function ($user, $password) {
                $user->password = $password;
                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __('messages.password_changed'));
        }

        throw ValidationException::withMessages([
            'email' => $this->statusMessage($status),
        ]);
    }

    /**
     * Map every stdlib Password broker status key to a project message key.
     *
     * Ponytail: one mapper for all 5 broker statuses (sent / reset /
     * throttled / invalid_user / invalid_token). Never interpolates the
     * raw 'passwords.*' value — the view only ever sees resolved text.
     */
    private function statusMessage(string $status): string
    {
        return match ($status) {
            Password::RESET_LINK_SENT => __('messages.reset_link_sent_simple'),
            Password::RESET_THROTTLED => __('messages.reset_link_throttled'),
            Password::PASSWORD_RESET => __('messages.password_changed'),
            Password::INVALID_USER => __('messages.reset_invalid_user'),
            Password::INVALID_TOKEN => __('messages.reset_invalid_token'),
            default => __('messages.could_not_send_reset_link'),
        };
    }
}
