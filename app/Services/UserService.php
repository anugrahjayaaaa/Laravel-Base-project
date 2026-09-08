<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;

/**
 * User lifecycle operations shared by the web and API controllers
 * (create / update / lock / unlock / reset-link). Keeps the domain logic
 * in one place so the two controllers don't drift apart.
 */
final class UserService
{
    public function create(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
        ]);
        $user->syncRoles($this->rolesFromInput($data));

        // per_user mode: provision a default Free license for the new user
        if (Setting::get('license_mode', 'global') === 'per_user') {
            LicenseService::defaultLicenseForUser($user);
        }

        return $user;
    }

    public function update(User $user, array $data): void
    {
        // ponytail: single update() call — avoids firing the `updated` observer twice
        // (password would otherwise trigger a second observer event after name/email).
        $payload = [
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
        ];
        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }
        $user->update($payload);
        $user->syncRoles($this->rolesFromInput($data));
    }

    /** @return list<int> */
    private function rolesFromInput(array $data): array
    {
        // ponytail: fall back to Setting 'default_role' when caller sends no roles
        // (self-service registration — doc auth.md §Self-service).
        if (! empty($data['roles'])) {
            return array_map('intval', $data['roles']);
        }
        $default = Setting::get('default_role');
        if (! $default) {
            return [];
        }
        $role = Role::where('name', $default)->whereNull('deleted_at')->first();

        return $role ? [(int) $role->id] : [];
    }

    public function lock(User $user): void
    {
        $user->update(['locked_until' => null, 'locked_permanently' => true]);

        // Invalidate all other active sessions for this user so locked accounts
        // cannot keep an already-authenticated session alive.
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->delete();
    }

    public function unlock(User $user): void
    {
        $user->update(['locked_until' => null, 'locked_permanently' => false]);
    }

    /**
     * @return string One of the Password::* status constants.
     */
    public function sendResetPassword(User $user): string
    {
        return Password::broker('users')->sendResetLink(['email' => $user->email]);
    }
}
