<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Auditable;
use App\Http\Requests\Profile\PasswordChangeRequest;
use App\Http\Requests\Profile\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    use Auditable;

    public function show(): View
    {
        return view('profile.show', ['user' => auth()->user()]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $user->update($request->validated());

        $this->audit($user, 'profile_updated', $user);

        return redirect()->route('profile.show')->with('success', __('messages.profile_updated'));
    }

    public function changePassword(PasswordChangeRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $user->update(['password' => Hash::make($request->validated()['password'])]);
        // revoke other sessions
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }
        auth()->logoutOtherDevices($request->validated()['password']);

        $this->audit($user, 'password_changed', $user);

        return redirect()->route('profile.show')->with('success', __('messages.password_changed'));
    }
}
