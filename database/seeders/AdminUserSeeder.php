<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the first-run super-admin user.
 *
 * SECURITY: this is DEVELOPMENT / FIRST-RUN seed data only.
 *  - email: admin@laravel-base.local  (RFC rfc2606-style .local placeholder)
 *  - password: Hash::make('#Password123')  (bcrypt-hashed, never stored
 *    in plaintext — this is seed-only default)
 *  - phone: masked '+628****0001'  (no real PII)
 *
 * In any real environment this is replaced by env-driven provisioning.
 * updateOrCreate is keyed on email so re-seeding only refreshes password
 * if you explicitly bump the value — safe to rerun idempotently.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'admin@laravel-base.local'],
            [
                'name' => 'Super Admin',
                'username' => 'superadmin',
                'phone' => '+628****0001',
                'password' => Hash::make('#Password123'),
                'email_verified_at' => now(),
            ]
        );

        $user->assignRole('super-admin');
    }
}
