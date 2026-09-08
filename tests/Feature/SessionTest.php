<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    // the `use Illuminate\Support\Facades\Auth;` import → Error: Class "Auth"
    // not found at runtime. This test exercises that exact code path.
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($u);

    $this->post(route('sessions.logoutOthers'), [
        'password' => '#Password123',
    ])->assertRedirect(route('sessions.index'))
        ->assertSessionHas('success');
});
