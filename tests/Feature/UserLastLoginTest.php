<?php

use App\Models\User;

beforeEach(fn () => $this->seed());

it('records last_login_at and last_login_ip on successful login', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $u->update(['last_login_at' => null, 'last_login_ip' => null]);

    $this->post(route('login.store'), ['identifier' => $u->email, 'password' => '#Password123'], [], ['REMOTE_ADDR' => '203.0.113.7'])
        ->assertRedirect(route('dashboard'));

    $u->refresh();
    expect($u->last_login_at)->not->toBeNull();
    expect($u->last_login_ip)->not->toBeNull(); // populated from request IP (127.0.0.1 under test server)
});
