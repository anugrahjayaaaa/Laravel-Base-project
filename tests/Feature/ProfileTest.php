<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => $this->seed());

it('shows own profile', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($u)
        ->get(route('profile.show'))
        ->assertOk()
        ->assertSee($u->name);
});

it('updates own name and phone', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($u)
        ->post(route('profile.update'), ['name' => 'New Name', 'phone' => '+6281234567890'])
        ->assertRedirect(route('profile.show'));

    expect($u->fresh()->name)->toBe('New Name');
    expect($u->fresh()->phone)->toBe('+6281234567890');
    expect(Activity::where('subject_id', $u->id)
        ->where('description', 'profile_updated')->exists())->toBeTrue();
});

it('rejects duplicate phone on own profile', function () {
    // admin phone is set in seeder; staff uses same -> conflict
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $existing = User::where('email', 'admin@laravel-base.local')->first(); // self
    // use a phone already owned by another seeded user to force uniqueness fail
    $other = User::factory()->create(['username' => uniqid('other'), 'phone' => '+62899999999']);
    $this->actingAs($u)
        ->post(route('profile.update'), ['name' => $u->name, 'phone' => $other->phone])
        ->assertSessionHasErrors('phone');
});

it('changes password with correct current password', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($u)
        ->post(route('profile.password'), [
            'current_password' => '#Password123',
            'password' => 'NewPass@12345',
            'password_confirmation' => 'NewPass@12345',
        ])
        ->assertRedirect(route('profile.show'));

    expect(Hash::check('NewPass@12345', $u->fresh()->password))->toBeTrue();
    expect(Activity::where('subject_id', $u->id)
        ->where('description', 'password_changed')->exists())->toBeTrue();
});

it('rejects password change with wrong current password', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($u)
        ->post(route('profile.password'), [
            'current_password' => 'wrong-password',
            'password' => 'NewPass@12345',
            'password_confirmation' => 'NewPass@12345',
        ])
        ->assertSessionHasErrors('current_password');
});

it('rejects password shorter than 12 chars', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($u)
        ->post(route('profile.password'), [
            'current_password' => '#Password123',
            'password' => 'short1',
            'password_confirmation' => 'short1',
        ])
        ->assertSessionHasErrors('password');
});
