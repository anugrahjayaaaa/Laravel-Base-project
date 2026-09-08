<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\Auditable;
use App\Http\Requests\Auth\LoginApiRequest;
use App\Http\Requests\Auth\PasswordChangeRequest;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use Auditable;
    private function resolveLoginField(string $identifier): string
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }

        if (preg_match('/^\+?\d{8,15}$/', $identifier)) {
            return 'phone';
        }

        return 'username';
    }

    public function login(LoginApiRequest $request): JsonResponse
    {
        $identifier = $request->identifier;
        $throttleKey = 'api-login:'.$request->ip().':'.strtolower($identifier);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json(['error' => ['code' => 'RATE_LIMITED', 'message' => __('messages.too_many_attempts', ['seconds' => $seconds])]], 429);
        }

        $field = $this->resolveLoginField($identifier);

        $user = User::where($field, $identifier)->first();

        if (! $user || $user->isLocked() || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, 900);

            throw ValidationException::withMessages(['identifier' => __('messages.invalid_credentials')]);
        }

        RateLimiter::clear($throttleKey);

        // ponytail: shared audit via Login event (same path as web)
        event(new Login('sanctum', $user, false));

        $token = $user->createToken($request->device_name, ['mobile'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => ['id' => $user->id, 'name' => $user->name, 'username' => $user->username, 'email' => $user->email],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => ['id' => $request->user()->id, 'name' => $request->user()->name, 'username' => $request->user()->username, 'email' => $request->user()->email],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('messages.logged_out')]);
    }

    public function changePassword(PasswordChangeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $request->user()->update(['password' => Hash::make($data['password'])]);
        $request->user()->tokens()->delete(); // revoke all mobile tokens

        return response()->json(['message' => __('messages.password_changed')]);
    }
}
