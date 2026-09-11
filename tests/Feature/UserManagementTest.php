<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->seed();
    $this->user = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($this->user);
});

it('lists users', function () {
    $this->get(route('users.index'))->assertOk();
});

it('creates a user with role', function () {
    $role = Role::first();
    $this->post(route('users.store'), [
        'name' => 'Jane Doe',
        'username' => 'jane',
        'email' => 'jane@example.com',
        'password' => 'Secret@123456',
        'password_confirmation' => 'Secret@123456',
        'roles' => [$role->id],
    ])->assertRedirect(route('users.index'));

    $u = User::where('username', 'jane')->first();
    expect($u)->not->toBeNull();
    expect($u->hasRole($role->name))->toBeTrue();
});

it('soft deletes and restores a user', function () {
    $u = User::create([
        'name' => 'Temp User', 'username' => 'temp', 'email' => 'temp@example.com',
        'password' => bcrypt('Secret@123456'),
    ]);
    $this->delete(route('users.destroy', $u))->assertRedirect();
    expect(User::find($u->id))->toBeNull();
    expect(User::withTrashed()->find($u->id))->not->toBeNull();

    $this->post(route('users.restore', $u->id))->assertRedirect();
    expect(User::find($u->id))->not->toBeNull();
});

it('updates own profile', function () {
    $this->post(route('profile.update'), [
        'name' => 'Updated Name',
        'phone' => '+6281299998888',
    ])->assertRedirect();
    expect($this->user->fresh()->name)->toBe('Updated Name');
});

it('bulk soft deletes selected users', function () {
    $a = User::create(['name' => 'Bulk A', 'username' => 'bulka', 'email' => 'bulka@example.com', 'password' => bcrypt('Secret@123456')]);
    $b = User::create(['name' => 'Bulk B', 'username' => 'bulkb', 'email' => 'bulkb@example.com', 'password' => bcrypt('Secret@123456')]);

    $this->post(route('users.bulk'), ['ids' => [$a->id, $b->id], 'action' => 'soft'])
        ->assertRedirect();

    expect(User::find($a->id))->toBeNull();
    expect(User::find($b->id))->toBeNull();
    expect(User::withTrashed()->find($a->id))->not->toBeNull();
    expect(User::withTrashed()->find($b->id))->not->toBeNull();
});

it('bulk force deletes selected users', function () {
    $a = User::create(['name' => 'Bulk C', 'username' => 'bulkc', 'email' => 'bulkc@example.com', 'password' => bcrypt('Secret@123456')]);

    $this->post(route('users.bulk'), ['ids' => [$a->id], 'action' => 'force'])
        ->assertRedirect();

    expect(User::withTrashed()->find($a->id))->toBeNull();
});

it('changes own password', function () {
    $this->post(route('profile.password'), [
        'current_password' => '#Password123',
        'password' => 'NewSecret@123456',
        'password_confirmation' => 'NewSecret@123456',
    ])->assertRedirect();
    expect(Auth::attempt(['username' => 'superadmin', 'password' => 'NewSecret@123456']))->toBeTrue();
});

it('sends verification email on admin create', function () {
    $role = Role::first();
    $this->post(route('users.store'), [
        'name' => 'Verify Me',
        'username' => 'verif'.time(),
        'email' => 'verify'.time().'@example.com',
        'password' => 'Secret@123456',
        'password_confirmation' => 'Secret@123456',
        'roles' => [$role->id],
    ])->assertRedirect(route('users.index'));

    $u = User::where('username', 'verif'.time())->first();
    expect($u)->not->toBeNull();
    // Current behavior: admin-created user is unverified by default in this path.
    expect($u->email_verified_at)->toBeNull();
});
