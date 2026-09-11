<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class)->beforeEach(fn () => $this->seed());

it('forgot password flashes a resolved status (no raw key) when email exists', function () {
    $user = User::first();
    config(['mail.driver' => 'log']);

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHas('status', __('messages.reset_link_sent_simple'));

    expect(session('status'))->not->toContain('passwords.');
});

it('forgot password shows a resolved message (no raw key) when email does not exist', function () {
    $this->post(route('password.email'), ['email' => 'nobody@example.test'])
        ->assertSessionHasErrors('email');

    $errors = session('errors');
    expect($errors->first('email'))->toBe(__('messages.reset_invalid_user'))
        ->and($errors->first('email'))->not->toContain('passwords.');
});

it('reset password with a valid token resets and flashes a resolved status', function () {
    $user = User::first();
    $plaintext = Str::random(64);
    DB::table('password_reset_tokens')->insert([
        'email' => $user->email,
        'token' => bcrypt($plaintext),
        'created_at' => now(),
    ]);

    $this->post(route('password.store'), [
        'token' => $plaintext,
        'email' => $user->email,
        'password' => '#Password123',
        'password_confirmation' => '#Password123',
    ])->assertRedirect(route('login'));

    // password changed in DB
    expect(Hash::check('#Password123', $user->fresh()->password))->toBeTrue();
});

it('reset password with an invalid token shows a resolved token error', function () {
    $user = User::first();
    DB::table('password_reset_tokens')->insert([
        'email' => $user->email,
        'token' => bcrypt('seeded-token-hashed'),
        'created_at' => now(),
    ]);

    $this->post(route('password.store'), [
        'token' => 'wrong-plaintext-token',
        'email' => $user->email,
        'password' => '#Password123',
        'password_confirmation' => '#Password123',
    ])->assertSessionHasErrors('email');

    $errors = session('errors');
    expect($errors->first('email'))->toBe(__('messages.reset_invalid_token'))
        ->and($errors->first('email'))->not->toContain('passwords.');
});
