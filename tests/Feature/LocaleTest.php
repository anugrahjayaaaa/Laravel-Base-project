<?php

use App\Http\Middleware\SetApiLocale;
use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Spatie\TranslationLoader\LanguageLine;

uses(RefreshDatabase::class);
beforeEach(fn () => $this->seed());

it('persists chosen locale in session', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($u)
        ->post(route('locale.update'), ['locale' => 'id'])
        ->assertRedirect();

    expect(session('locale'))->toBe('id');
});

it('SetLocale middleware applies session locale to the app', function () {
    Session::put('locale', 'id');
    $request = Request::create('/dashboard');
    $called = false;
    (new SetLocale)->handle($request, function ($req) use (&$called) {
        $called = true;
        expect(App::getLocale())->toBe('id');
        expect(ui('users'))->toBe('Pengguna');
    });
    expect($called)->toBeTrue();
});

it('switches UI language end-to-end via session', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($u);

    // default locale is English
    $this->get(route('dashboard'))->assertSee('Dashboard');

    // choose Indonesian -> session persists to the next request
    $this->post(route('locale.update'), ['locale' => 'id'])->assertRedirect();

    // SetLocale must read the session *after* StartSession, so the menu renders ID
    $this->get(route('dashboard'))->assertSee('Dasbor');
});

it('rejects unsupported locale', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $this->actingAs($u)
        ->post(route('locale.update'), ['locale' => 'fr'])
        ->assertRedirect();

    // locale must NOT be persisted when invalid
    expect(session('locale'))->not->toBe('fr');
});

it('resolves API locale from X-Locale header', function () {
    $this->withHeader('X-Locale', 'id')
        ->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertJson(fn ($json) => $json->whereType('message', 'string')->etc());

    // resolve via the middleware directly to confirm locale is applied (no auth needed)
    $request = Request::create('/api/v1/me', 'GET');
    $request->headers->set('X-Locale', 'id');
    $called = false;
    (new SetApiLocale)->handle($request, function ($req) use (&$called) {
        $called = true;
        expect(App::getLocale())->toBe('id');
    });
    expect($called)->toBeTrue();
});

it('rejects unsupported API locale', function () {
    $request = Request::create('/api/v1/me', 'GET');
    $request->headers->set('X-Locale', 'fr');
    $called = false;
    (new SetApiLocale)->handle($request, function ($req) use (&$called) {
        $called = true;
        expect(App::getLocale())->toBe('en'); // falls back
    });
    expect($called)->toBeTrue();
});

it('returns localized API message end-to-end (login + logout)', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();

    $login = $this->postJson('/api/v1/login', [
        'identifier' => $u->username,
        'password' => '#Password123', // seeded default
        'device_name' => 'e2e-test',
    ])->assertOk();

    $token = $login->json('token');
    expect($token)->not->toBeNull();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->withHeader('X-Locale', 'id')
        ->postJson('/api/v1/logout')
        ->assertOk()
        ->assertJson(['message' => 'Berhasil keluar.']); // id translation of messages.logged_out
});

it('returns English API message without X-Locale', function () {
    $u = User::where('email', 'admin@laravel-base.local')->first();
    $token = $u->createToken('e2e')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/logout')
        ->assertOk()
        ->assertJson(['message' => 'Logged out.']);
});

it('reads translations from database (language_lines)', function () {
    // seeded by DatabaseSeeder -> LanguageLineSeeder
    $line = LanguageLine::where('group', 'ui')->where('key', 'users')->first();
    expect($line)->not->toBeNull();
    expect($line->text)->toBe(['en' => 'Users', 'id' => 'Pengguna']);

    // DB overrides the file: ui() returns DB value
    Session::put('locale', 'id');
    $request = Request::create('/dashboard');
    (new SetLocale)->handle($request, function ($req) {
        expect(ui('users'))->toBe('Pengguna');
    });
});
