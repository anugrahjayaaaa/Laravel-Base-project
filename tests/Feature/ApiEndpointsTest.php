<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed());

function apiToken(User $u): string
{
    return $u->createToken('test', ['mobile'])->plainTextToken;
}

it('logs in and returns a bearer token', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $r = $this->postJson('/api/v1/login', [
        'identifier' => $u->email,
        'password' => '#Password123',
        'device_name' => 'test',
    ]);
    $r->assertOk()->assertJsonStructure(['token', 'user']);
});

it('rejects invalid credentials', function () {
    $this->postJson('/api/v1/login', [
        'identifier' => 'admin@laravel-base.local',
        'password' => 'wrong',
        'device_name' => 'test',
    ])->assertStatus(422);
});

it('returns 401 without token', function () {
    $this->getJson('/api/v1/me')->assertStatus(401);
});

it('me endpoint works with token', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $this->withHeader('Authorization', 'Bearer '.apiToken($u))
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('user.email', $u->email);
});

it('lists users with token', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $this->withHeader('Authorization', 'Bearer '.apiToken($u))
        ->getJson('/api/v1/users')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('creates a user via API', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $r = $this->withHeader('Authorization', 'Bearer '.apiToken($u))
        ->postJson('/api/v1/users', [
            'name' => 'Api User',
            'username' => 'apiuser',
            'email' => 'apiuser@laravel-base.local',
            'password' => 'Secret123456',
            'password_confirmation' => 'Secret123456',
            'roles' => [],
        ]);
    $r->assertCreated()->assertJsonPath('username', 'apiuser');
    expect(User::where('username', 'apiuser')->exists())->toBeTrue();
});

it('returns notifications and marks read', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $this->withHeader('Authorization', 'Bearer '.apiToken($u))
        ->getJson('/api/v1/notifications')
        ->assertOk();
});

it('returns audit list', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $this->withHeader('Authorization', 'Bearer '.apiToken($u))
        ->getJson('/api/v1/audit')
        ->assertOk();
});

it('lists features', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $this->withHeader('Authorization', 'Bearer '.apiToken($u))
        ->getJson('/api/v1/features')
        ->assertOk();
});

it('logs session_invalidated when API lock is called', function () {
    $admin = User::where('email', 'admin@laravel-base.local')->first();
    $target = User::factory()->create(['username' => 'apilock'.time()]);

    $this->withHeader('Authorization', 'Bearer '.apiToken($admin))
        ->postJson('/api/v1/users/'.$target->id.'/lock')
        ->assertOk();

    expect(Activity::where('subject_id', $target->id)
        ->where('description', 'session_invalidated')->exists())->toBeTrue();
});

it('logs session_logout_others from API session logout', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $sid = \Illuminate\Support\Str::random(40);
    DB::table('sessions')->insertOrIgnore([
        'id' => $sid,
        'user_id' => $u->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'testing',
        'last_activity' => time(),
    ]);

    $this->withHeader('Authorization', 'Bearer '.apiToken($u))
        ->postJson('/api/v1/sessions/logout-others')
        ->assertOk();

    expect(Activity::where('subject_id', $u->id)
        ->where('description', 'session_logout_others')->exists())->toBeTrue();
});

it('logs profile_updated via API profile update', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $this->withHeader('Authorization', 'Bearer '.apiToken($u))
        ->putJson('/api/v1/profile', ['name' => 'API Name', 'phone' => '+6281234567890'])
        ->assertOk();

    expect($u->fresh()->name)->toBe('API Name');
    expect(Activity::where('subject_id', $u->id)
        ->where('description', 'profile_updated')->exists())->toBeTrue();
});

it('logs password_changed via API password change', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $this->withHeader('Authorization', 'Bearer '.apiToken($u))
        ->postJson('/api/v1/profile/password', [
            'current_password' => '#Password123',
            'password' => 'NewPass@12345',
            'password_confirmation' => 'NewPass@12345',
        ])->assertOk();

    expect($u->fresh()->password)->not->toBe(Hash::make('#Password123'));
    expect(Activity::where('subject_id', $u->id)
        ->where('description', 'password_changed')->exists())->toBeTrue();
});
