<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\UserService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

/**
 * Self-service registration.
 *
 * Gate: the /register route is only live when Setting::get('registration_enabled')
 * is truthy — see routes/web.php. When disabled the route 404s (fail-closed).
 *
 * Registration does NOT assign roles by default (matches docs: new self-service
 * users get the default role seeded, no permission to manage access). Super-admin
 * is seeded separately; self-register users start with zero custom roles.
 *
 * @see docs/auth.md §Self-service
 */
class RegisterController extends Controller
{
    /**
     * Show the registration form.
     */
    public function show()
    {
        return view('auth.register');
    }

    /**
     * Handle a registration request — thin: validate (Form Request) + create
     * (UserService) + fire Registered event for verification email.
     */
    public function store(RegisterRequest $request)
    {
        // ponytail: single transaction so a partial insert never leaves a dangling user
        return DB::transaction(function () use ($request) {
            $user = app(UserService::class)->create($request->validated());

            // ponytail: manually send verification (Laravel auto-listener may register
            // twice causing duplicate emails); direct call guarantees single dispatch).
            $user->sendEmailVerificationNotification();

            return redirect()->route('login')
                ->with('status', __('messages.registration_success_verify_email'));
        });
    }
}
