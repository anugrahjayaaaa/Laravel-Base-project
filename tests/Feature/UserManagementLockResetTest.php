<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => $this->seed());

it('updates roles and password in one request', function () {
    $admin = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($admin);
    $u = User::factory()->create(['username' => 'upd'.time()]);
    $role = Role::where('name', 'staff')->first();

    $this->put(route('users.update', $u), [
        'name' => $u->name,
        'username' => $u->username,
        'email' => $u->email,
        'phone' => $u->phone,
        'password' => 'NewPass@12345',
        'password_confirmation' => 'NewPass@12345',
        'roles' => [$role->id],
    ])->assertRedirect(route('users.index'));

    $u->refresh();
    expect($u->hasRole('staff'))->toBeTrue();
    expect(Hash::check('NewPass@12345', $u->password))->toBeTrue();
});

it('unlocks a locked account', function () {
    $admin = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($admin);
    $u = User::factory()->create(['username' => 'lock'.time(), 'locked_until' => now()->addMinutes(15)]);

    expect($u->isLocked())->toBeTrue();

    $this->post(route('users.unlock', $u))
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    expect($u->fresh()->isLocked())->toBeFalse();
});

it('sends a reset link (broker returns sent)', function () {
    $admin = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($admin);
    $u = User::factory()->create(['username' => 'reset'.time()]);

    // MAIL_MAILER=log in test env -> broker still returns RESET_LINK_SENT
    $this->post(route('users.reset-password', $u))
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');
});

it('logs unlock and reset-link actions to audit', function () {
    $admin = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($admin);
    $u = User::factory()->create(['username' => 'aud'.time(), 'locked_until' => now()->addMinutes(5)]);

    $this->post(route('users.unlock', $u));
    $this->post(route('users.reset-password', $u));

    $descs = Activity::where('subject_id', $u->id)
        ->pluck('description')->toArray();
    expect($descs)->toContain('user_unlocked');
    expect($descs)->toContain('user_reset_link_sent');
});

it('permanently locks an account (user.lock permission, self excluded)', function () {
    $admin = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($admin);

    // Self-lock is blocked (cannot lock yourself).
    $this->post(route('users.lock', $admin))
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('error');
    expect($admin->fresh()->isLocked())->toBeFalse();

    $u = User::factory()->create(['username' => 'plk'.time()]);
    $this->post(route('users.lock', $u))
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    $locked = $u->fresh();
    expect($locked->isLocked())->toBeTrue();
    expect($locked->isPermanentlyLocked())->toBeTrue();
    expect($locked->locked_permanently)->toBeTrue(); // flag, not a sentinel date
    expect(Activity::where('subject_id', $u->id)
        ->where('description', 'user_locked')->exists())->toBeTrue();
    expect(Activity::where('subject_id', $u->id)
        ->where('description', 'session_invalidated')->exists())->toBeTrue();

    // Unlock clears the permanent flag.
    $this->post(route('users.unlock', $u))->assertRedirect(route('users.index'));
    expect($u->fresh()->isPermanentlyLocked())->toBeFalse();
    expect($u->fresh()->isLocked())->toBeFalse();
});

it('blocks login for a permanently locked account with admin message', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $u->update(['password' => Hash::make('RightPass@12345'), 'locked_until' => null, 'locked_permanently' => true]);

    $this->post(route('login.store'), ['identifier' => $u->email, 'password' => 'RightPass@12345'])
        ->assertSessionHasErrors('identifier');

    $errors = session('errors')->get('identifier');
    expect($errors[0])->toContain('permanently locked');
});

it('staff without user.lock cannot lock an account', function () {
    $staff = User::factory()->create(['username' => 'lkst'.time()]);
    $staff->assignRole('staff'); // only user.view, audit.view
    $target = User::factory()->create(['username' => 'lkta'.time()]);

    $this->actingAs($staff)
        ->post(route('users.lock', $target))
        ->assertForbidden();

    expect($target->fresh()->isLocked())->toBeFalse();
});
