<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;

uses(RefreshDatabase::class);
beforeEach(fn () => $this->seed());

it('denies logs page to unauthenticated', function () {
    $this->get('/logs')->assertRedirect('/login');
});

it('shows logs page to user with logs.view', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($u)
        ->get('/logs')
        ->assertOk()
        ->assertSee('System Logs'); // our AdminLTE view title
});

it('forbids logs page to user without logs.view', function () {
    $staff = User::where('username', 'staff')->first()
        ?? User::factory()->create(['username' => 'staff2', 'email' => 'staff2@laravel-base.local']);
    $staff->assignRole('staff'); // staff has only user.view + audit.view
    $this->actingAs($staff)->get('/logs')->assertForbidden();
});

it('blocks a logs.view holder when the flag is off (404)', function () {
    $role = Role::findOrCreate('log-reader', 'web');
    $role->syncPermissions(['logs.view']); // logs.view WITHOUT feature.manage
    $u = User::factory()->create(['username' => 'logreader'.time()]);
    $u->assignRole($role);

    $this->actingAs($u)->get('/logs')->assertOk();

    Feature::deactivate('logs');
    $this->actingAs($u)->get('/logs')->assertNotFound();
});

it('lets a feature.manage holder reach logs while the flag is off', function () {
    // ponytail: kill-switch — disabled flag blocks everyone, including managers.
    Feature::deactivate('logs');
    $u = User::where('email', 'admin@laravel-base.local')->first(); // holds feature.manage

    $this->actingAs($u)->get('/logs')->assertNotFound();
});

it('renders a search input on logs page', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($u)
        ->get('/logs')
        ->assertOk()
        ->assertSee('name="q"', false);
});
