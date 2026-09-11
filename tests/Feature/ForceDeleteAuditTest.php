<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => $this->seed());

// ponytail: controller forceDelete emits exactly one audit row via the
// Auditable trait (covered in AuditTest USER-01). These two tests pin the
// NON-HTTP observer fallback path (tinker / CLI / seeders) so forceDelete
// is never silently un-audited outside the web layer.

it('logs role force-delete via observer (non-HTTP fallback)', function () {
    Auth::logout();
    $role = Role::create(['name' => 'tmp_audit_fd', 'guard_name' => 'web']);
    $role->delete();
    $role->forceDelete();
    expect(Activity::where('description', 'role_force_deleted')->where('subject_id', $role->id)->exists())->toBeTrue();
});

it('logs user force-delete via observer (non-HTTP fallback)', function () {
    Auth::logout();
    $u = User::create(['name' => 'Tmp', 'username' => 'tmp_fd', 'email' => 'tmp_fd@example.com', 'password' => bcrypt('Secret@123456')]);
    $u->delete();
    $u->forceDelete();
    expect(Activity::where('description', 'user_force_deleted')->where('subject_id', $u->id)->exists())->toBeTrue();
});
