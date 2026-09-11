<?php

use App\Models\User;

beforeEach(function () {
    $this->seed();
});

it('logs in via api and returns token', function () {
    $response = $this->postJson('/api/v1/login', [
        'identifier' => 'superadmin',
        'password' => '#Password123',
        'device_name' => 'test-device',
    ]);
    $response->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'username']]);
    expect($response->json('token'))->not->toBeEmpty();
});

it('rejects bad credentials', function () {
    $this->postJson('/api/v1/login', [
        'identifier' => 'superadmin',
        'password' => 'wrong',
        'device_name' => 'x',
    ])->assertStatus(422);
});

it('rejects login for locked account', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $u->update(['locked_until' => now()->addDay(), 'locked_permanently' => false]);

    $this->postJson('/api/v1/login', [
        'identifier' => $u->username,
        'password' => '#Password123',
        'device_name' => 'x',
    ])->assertStatus(422);
});

it('requires auth for me', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('returns me with valid token', function () {
    $user = User::where('email', 'admin@laravel-base.local')->first();
    $token = $user->createToken('dev')->plainTextToken;
    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('user.username', 'superadmin');
});

it('logs out and revokes token', function () {
    $user = User::where('email', 'admin@laravel-base.local')->first();
    $token = $user->createToken('dev')->plainTextToken;
    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/logout')
        ->assertOk();
    expect($user->fresh()->tokens()->count())->toBe(0);
});
