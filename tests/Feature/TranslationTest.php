<?php

use App\Http\Middleware\SetLocale;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Spatie\TranslationLoader\LanguageLine;

uses(RefreshDatabase::class);
beforeEach(fn () => $this->seed());

it('lists translations for admin', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($u)
        ->get(route('translations.index'))
        ->assertOk()
        ->assertSee('messages') // group column
        ->assertSee('user_created');    // key column (rendered in <code>)
});

it('updates a translation value and reflects in __()', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $line = LanguageLine::where('group', 'messages')->where('key', 'user_created')->first();

    $this->actingAs($u)
        ->put(route('translations.update', $line), ['en' => 'Users', 'id' => 'Pengguna Edit'])
        ->assertRedirect(route('translations.index'));

    $line->refresh();
    expect($line->text['id'])->toBe('Pengguna Edit');

    session(['locale' => 'id']);
    $request = request()->create('/dashboard');
    app(SetLocale::class)->handle($request, function ($req) {
        expect(__('messages.user_created'))->toBe('Pengguna Edit');
    });
});

it('denies translations to unauthorized user', function () {
    $user = User::factory()->create(['username' => 'unauth'.time()]);
    $this->actingAs($user)
        ->get(route('translations.index'))
        ->assertForbidden();
});

it('blocks a translation.view holder when the flag is off (404)', function () {
    $role = Role::findOrCreate('translator', 'web');
    $role->syncPermissions(['translation.view']);
    $u = User::factory()->create(['username' => 'translator'.time()]);
    $u->assignRole($role);

    // flag on -> allowed
    $this->actingAs($u)->get(route('translations.index'))->assertOk();

    // flag off -> 404 (fail-closed), not a permission error
    Feature::deactivate('translations');
    $this->actingAs($u)->get(route('translations.index'))->assertNotFound();
});

it('lets a feature.manage holder reach translations while the flag is off', function () {
    // ponytail: kill-switch — disabled flag blocks everyone, including managers.
    Feature::deactivate('translations');
    $u = User::where('email', 'admin@laravel-base.local')->first(); // holds feature.manage
    $this->actingAs($u)->get(route('translations.index'))->assertNotFound();
});

it('keeps en and id locales structurally consistent', function () {
    // ponytail: guard against drift — every group/key must match across locales, placeholders included.
    $locales = config('app.available_locales');
    $groups = ['ui', 'messages'];

    foreach ($groups as $group) {
        $data = [];
        foreach ($locales as $locale) {
            $path = lang_path("{$locale}/{$group}.php");
            if (! file_exists($path)) {
                continue;
            }
            $data[$locale] = require $path;
        }

        $base = $data['en'] ?? [];
        foreach ($base as $key => $val) {
            if (is_array($val)) {
                continue;
            } // skip Laravel validation sub-arrays
            expect(isset($data['id'][$key]))->toBeTrue("KEY MISSING in id/{$group}:{$key}");
            $enPh = preg_match_all('/:(\w+)/', $val);
            $idPh = isset($data['id'][$key]) ? preg_match_all('/:(\w+)/', $data['id'][$key]) : 0;
            expect($idPh)->toBe($enPh, "PLACEHOLDER MISMATCH in id/{$group}:{$key}");
        }
        foreach ($data['id'] ?? [] as $key => $val) {
            if (is_array($val)) {
                continue;
            }
            expect(isset($base[$key]))->toBeTrue("EXTRA key in id/{$group}:{$key}");
        }
    }
});
