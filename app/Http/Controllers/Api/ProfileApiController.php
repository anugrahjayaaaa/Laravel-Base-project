<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\Auditable;
use App\Http\Requests\Profile\PasswordChangeRequest;
use App\Http\Requests\Profile\ProfileUpdateRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * @group Profile
 *
 * Authenticated user's own profile.
 */
class ProfileApiController extends Controller
{
    use Auditable;
    /** Show own profile. */
    public function show(Request $request): JsonResponse
    {
        return response()->json(new UserResource($request->user()->load('roles')));
    }

    /** Update own profile (name, username, email, phone).
     * @authenticated
     */
    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        $this->audit($user, 'profile_updated', $user);

        return response()->json(new UserResource($user->load('roles')));
    }

    /** Change own password (revokes all tokens + other devices). */
    public function changePassword(PasswordChangeRequest $request): JsonResponse
    {
        $user = $request->user();
        $request->user()->update(['password' => Hash::make($request->validated()['password'])]);
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->audit($user, 'password_changed', $user);

        return response()->json(['message' => __('messages.password_changed')]);
    }
}
