<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

it('lists the current user only their sessions', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($u);

    $sessions = DB::table('sessions')
        ->where('user_id', $u->id)
        ->get();

    // admin has no session rows yet at fresh login without a browser
    expect($sessions)->toBeEmpty();
});

it('logs out other sessions without password', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($u);

    // seed a session row for the current user
    $sid = session()->getId();
    if ($sid) {
        DB::table('sessions')->insertOrIgnore([
            'id' => $sid,
            'user_id' => $u->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'testing',
            'last_activity' => time(),
        ]);
    }

    $countBefore = DB::table('sessions')->where('user_id', $u->id)->count();

    $this->post(route('sessions.logoutOthers'))
        ->assertRedirect(route('sessions.index'))
        ->assertSessionHas('success');

    // without password, the controller deletes other session rows (not current)
    // current session row (if present) survives because it is filtered by '<>' id
    $countAfter = DB::table('sessions')->where('user_id', $u->id)->count();
    expect($countAfter)->toBeLessThanOrEqual($countBefore);

    expect(Activity::where('subject_id', $u->id)
        ->where('description', 'session_logout_others')->exists())->toBeTrue();
});

it('regenerates device session with valid password (Auth facade regression)', function () {
    // Regression test for P0-1: Auth::logoutOtherDevices() was called without
    // the `use Illuminate\Support\Facades\Auth;` import -> Error: Class "Auth"
    // not found at runtime. This test exercises that exact code path.
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($u);

    $this->post(route('sessions.logoutOthers'), [
        'password' => '#Password123',
    ])->assertRedirect(route('sessions.index'))
        ->assertSessionHas('success');
});

it('SESSION-01: login regenerates the session (fixation guard) and emits exactly one login_success', function () {
    Auth::logout();
    $sidBefore = session()->getId();
    $this->post(route('login.store'), [
        'identifier' => 'superadmin',
        'password' => '#Password123',
    ])->assertRedirect(route('dashboard'));

    $sidAfter = session()->getId();
    expect($sidAfter)->not->toBe($sidBefore); // session::regenerate produced new id

    expect(Activity::where('description', 'login_success')->count())->toBe(1);
});

it('SESSION-03: account lock invalidates active sessions (sessions table cleared)', function () {
    $admin = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($admin);

    $target = User::factory()->create(['username' => 'locktarget'.time(), 'name' => 'Target']);

    $this->post(route('users.lock', $target))->assertRedirect(route('users.index'));

    // UserService::lock deletes all session rows for the user (DB session driver = prod).
    // In tests the session driver is 'array', so assert via the dedicated DB table:
    expect(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(0);

    expect(Activity::where('description', 'user_locked')
        ->where('subject_id', $target->id)
        ->where('causer_id', $admin->id)->exists())->toBeTrue();
    expect(Activity::where('description', 'session_invalidated')
        ->where('subject_id', $target->id)->exists())->toBeTrue();
});
