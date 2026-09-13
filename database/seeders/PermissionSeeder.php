<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Seeds the permission catalog (spatie/laravel-permission, 'web' guard).
 *
 * Source: the single reference list that gates every route via `can:`.
 * Uses Permission::findOrCreate (spatie) so it is naturally idempotent.
 */
class PermissionSeeder extends Seeder
{
    /** @var list<string> */
    private const PERMISSIONS = [
        // users
        'user.view', 'user.create', 'user.edit', 'user.delete', 'user.restore',
        'user.force-delete', 'user.lock', 'user.manage',
        // roles
        'role.view', 'role.create', 'role.edit', 'role.delete', 'role.restore',
        'role.force-delete', 'role.manage',
        // permissions
        'permission.view', 'permission.create', 'permission.edit', 'permission.delete',
        'permission.restore', 'permission.force-delete', 'permission.manage',
        // audit / monitoring
        'audit.view',
        'session.view', 'session.revoke',
        'logs.view',
        // settings
        'api-token.view', 'api-token.create', 'api-token.delete',
        'translation.view', 'translation.create', 'translation.edit',
        // feature management
        'feature.manage',
        // billing
        'billing.view', 'billing.cancel',
        // tooling (devops)
        'telescope.view', 'periscope.view',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
    }
}
