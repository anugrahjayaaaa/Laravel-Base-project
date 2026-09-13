# Backend Code Quality Scan — report (Ponytail full)

Branch: feature/general-fixes (clean, 3 commits ahead of origin).
Scan date: 2026-09-04. Target: Laravel Base Project fork.

Tujuan: periksa apakah kode custom (controller/service/model/request/resource/middleware)
sulit diikuti / melanggar konvensi di AGENTS.md, docs/coding-standard.md, PRD.md.
Fokus: variable/function/class naming, PSR-12, strict_types, inline-validate,
hardcoded EN, authorization placement, controller thinness, resource pattern.

## Ringkasan eksekutif

Kondisi umum baik. Pola sudah konsisten (route-gated authz, Form Request, service
layer, observer audit, i18n dual-source via ui()/__('messages.*')). 15 file gagal
linting Pint — semuanya *style*, bukan fatal. Ada 2 pelanggaran struktural ringan
yang sebaiknya diperbaiki. Tidak ada pelanggaran keamanan kritis.

## 1. Konvensi yang sudah baik (patut dipertahankan)

- Authorization: semua route protected via `can:perm` + `feature:slug` middleware
  di routes/web.php. Tidur tidak ada `$this->middleware()` di controller __construct()
  kecuali untuk *dependency injection* (UserController, RoleController, PermissionController,
  AuditController — ini DI, bukan authz gate; sah).
- Validation: hampir semua pakai Form Request yang dikelompokkan per domain
  (Auth/, User/, Rbac/, Profile/, ApiToken/, Session/, Translation/, Plan/, Billing/).
  authorize() non-trivial pada resource protected (permission.create, role.edit, dsb).
- Controller thin: logika bisnis di Services/*. LicenseService, BillingService,
  PlanService, UserService, AuditQueryService, BulkDeleteService. Controller hanya
  validate+call+view/redirect.
- i18n: __().__('messages.*') untuk domain/API, ui() untuk UI page text.
  TranslationController.update pakai __('messages.saved') ?? 'Saved.' — ini *sedikit*
  rentan (lih. §2 below).
- Audit: observer (Permission/Role/User) + listener (LogAuthentication) konsisten.
  Secret-never-log: `unset($dirty['password'])` di semua observer.
- Naming: PascalCase class, snake_case DB, camelCase JS, kebab resource/controller.
  Permission slugs resource.action — konsisten.
- Strict types: belum `declare(strict_types=1)` di semua file (lih. §2).

## 2. Pelanggaran & rekomendasi (diurut, Prioritas 1 = serius)

### [P1] SettingsController.update() pakai inline `$request->validate()` — melanggar coding-standard.md §3

File: app/Http/Controllers/SettingsController.php:42

```php
$request->validate([
    'locale_default' => ['required', 'string', 'in:' . ...],
    ...
]);
```

AGENTS.md + coding-standard.md eksplisit: "No `$request->validate()` inside
controllers. Use Form Request." Ini pelanggaran langsung. Meskipun komentar
koding menyebut "minimal validation — no FormRequest needed", konvensi tim
tidak punya pengecualian untuk ini. Route ini gated `can:feature.manage`, jadi
authz sudah benar — hanya validation placement-nya salah.

Rekomendasi (1 file, ~25 baris): buat FormRequest `Settings/SystemSettingsRequest`
di `app/Http/Requests/Settings/`, pindahkan rules() ke sana, controller panggil
`$request->validated()`. authorize() return `$this->user()->can('feature.manage')`.

### [P1] TranslationController.update() pakai fallback hardcoded EN — rentan silent i18n bug

File: app/Http/Controllers/TranslationController.php:48

```php
->with('status', __('messages.saved') ?? 'Saved.');
```

Ini *tepat* pola yang AGENTS.md warning-kan: "i18n dual-source CONFIRMED: ...
Mixing them = silent null→English fallback BUG. Never hardcode English ?? '...' in blade."
`__('messages.saved')` *berhasrat ke messages.php*; kalau key tak ada, Laravel
mengembalikan key itu sendiri (bukan null), jadi `?? 'Saved.'` takkan pernah
trigger — tapi bila spatie loader mengembalikan null, fallback ke hardcoded EN
terjadi *bug diam*. Lebih penting: translation edit adalah domain UI, seharusnya
`ui('saved')` atau `__('messages.saved')` konsisten, bukan mix.

Rekomendasi: hapus fallback hardcoded, pakai `__('messages.saved')` atau `ui('saved')`
sesuai key mana yang ada di lang/{en,id}/. Cek lang file.

### [P1] Resource controller ApiTokenController.destroy() / ApiTokenApiController.destroy() pakai `int $token` di route — tidak RESTful, potensi ID guessing

File: app/Http/Controllers/ApiTokenController.php:31, Api/Token:37

```php
public function destroy(int $token): RedirectResponse
// route: DELETE api-tokens/{token}
```

FormRequest yang sah bisa dipakai; dengan `int` langsung, otorisasi token milik user
hanya implisit lewat query builder (`->where('id', $token)`). Ini *sudah cukup* karena
query scoped ke user, tapi pola konsisten tim pakai FormRequest untuk semua mutation.
Minor — tapi konsistensi → gunakan Route param type-hint model binding atau setidaknya
validasi eksplisit.

### [P2] Model User: atribut `phone_verified_at` declared di casts tapi tidak ada kolom di migrasi

File: app/Models/User.php:45, migrasi 0001_01_01_000000_create_users_table.php

Casts punya `'phone_verified_at' => 'datetime'`, tapi migrasi tidak punya kolom
`phone_verified_at`. Laravel 13 + MustVerifyEmail tidak otomatis. Ini *silent bug*
jika kode pakai `$user->phone_verified_at`. Perlu konfirmasi migrasi vs model —
jika disengaja (pending feature), tambahkan comment. Jika tidak, hapus casts.

### [P2] Payment::isCanceled() cek `canceled_at !== null` — tapi column tidak di fillable

File: app/Models/Payment.php:11, :41

`canceled_at` tidak ada di `$fillable` dan tidak di casts, tapi dipakai di
isCanceled(). Ini *bisa* jalan via direct DB update (BillingService.cancelUser
menggunakan `->update(['canceled_at' => now()])`). Tapi karena tidak fillable,
mass-assignment gagal diam. Perlu verifikasi: apakah `canceled_at` ada di migrasi?
Jika ya, tambahkan ke fillable. Jika tidak, ini logic bug.

### [P3] bcrypt() dipakai 5x — Laravel 13 merekomendasikan Hash facade (bcrypt helper tidak deprecated tapi tidak disarankan)

Files: UserService.php:24,42, ProfileController.php:28, Api/AuthController.php:46/81,
Api/ProfileApiController.php:39, Api/AuthApiController.php:61

`bcrypt()` helper masih ada di L13 (Illuminate\Foundation\helpers) dan tidak deprecated,
tapi dokumentasi resmi & Pint preset menyarankan `Hash::make()`. Inkonsistensi:
AuthController pakai `Hash::check()` (benar) tapi `bcrypt()` untuk make.

Rekomendasi: ganti ke `Hash::make()` + import `use Illuminate\Support\Facades\Hash;`
untuk konsistensi. Ini style-level, bukan bug.

### [P3] PlanService::filterPermissions() dan RoleController: permission filtering logika duplikat

RoleController::filterPermissions() (private method) duplikat konsep di
PlanService::allowedPermissions(). Bisa diekstrak ke Permission::featureOf()
atau method static, tapi ini *desain choice* — duplikasi kecil, bukan teknis
debt krusial. Biarkan sampai ada kebutuhan DRY yang nyata.

### [P3] FeatureController.toggle() / Api toggle: pakai `?->` ternary — cukup tapi bisa lebih jelas

```php
$request->boolean('enabled') ? Feature::activate($slug) : Feature::deactivate($slug);
```

Bisa `match($request->boolean('enabled'))` tapi ternary sudah jelas dan bekerja.
Bukan pelanggaran.

## 3. Pint linting — 15 file, SEMUA style issue (bukan fatal)

Berikut pemecahan Pint fixers yang melaporkan (semua style, bukan error PHP):

| File | Fixers | Tipe |
|------|--------|------|
| app/Enums/LicenseMode.php | single_space_around_construct, single_blank_line_at_eof | style |
| app/Models/Payment.php | blank_line_before_statement | style |
| app/Models/Permission.php | blank_line_before_statement | style |
| app/Http/Middleware/SetLocale.php | fully_qualified_strict_types, unary_operator_spaces, not_operator_with_successor_space, ordered_imports | style |
| app/Http/Requests/Auth/RegisterRequest.php | no_unused_imports | style |
| app/Http/Requests/Plan/PlanRequest.php | fully_qualified_strict_types, ordered_imports | style |
| app/Http/Controllers/BillingController.php | fully_qualified_strict_types, unary_operator_spaces, not_operator_with_successor_space, ordered_imports | style |
| app/Http/Controllers/DashboardController.php | fully_qualified_strict_types | style |
| app/Http/Controllers/RoleController.php | unary_operator_spaces, braces_position, not_operator_with_successor_space, single_line_empty_body, blank_line_before_statement | style |
| app/Http/Controllers/SettingsController.php | concat_space, no_unused_imports | style |
| app/Http/Controllers/BillingAdminController.php | no_unused_imports | style |
| app/Services/LicenseService.php | unary_operator_spaces, no_unused_imports, not_operator_with_successor_space, blank_line_before_statement | style |
| app/Services/PlanService.php | unary_operator_spaces, not_operator_with_successor_space, blank_line_before_statement | style |
| app/Services/BillingService.php | unary_operator_spaces, not_operator_with_successor_space, blank_line_before_statement | style |
| app/Services/UserService.php | unary_operator_spaces, not_operator_with_successor_space, blank_line_before_statement | style |

Rekomendasi: `php vendor/bin/pint` (auto-fix). Tidak ada risk breaking logic —
semua fixer adalah pemformatan.

## 4. `declare(strict_types=1)` — tidak ada di SEMUA file (0/70+ file)

grep hasil: `grep -rl "declare(strict_types" app/ tests/` = 0 file.
coding-standard.md §6 eksplisit: "PSR-12, `declare(strict_types=1)`, type hints required."

Ini pelanggaran konvensi yang cukup. Tapi karena ini *semua* file, ini adalah
keputusan tim — jika belum di adopt, jangan di-enforce sebagian. Catat sebagai
technical debt tim, bukan bug individu.

## 5. Variable naming — konsisten, tidak ada issue

- camelCase lokal ($paidThisMonth, $activeLicenseQuery, $planBreakdown, $allowedNames)
- snake_case DB / request input konsisten
- Parameter type-hint lengkap di semua method public
- Tidak ada variable aneh, singkatan atau shadow

## 6. Class naming — konsisten

- Controller: PascalCase, *Controller suffix (PlanController, BillingAdminController)
- Service: PascalCase, *Service (BillingService, PlanService)
- Model: PascalCase, singular (Plan, License, Payment)
- Request: PascalCase, *Request (PlanRequest, BillingCancelRequest)
- Resource: PascalCase, *Resource (UserResource, FeatureResource)
- Middleware: PascalCase, descriptive (EnsureFeatureEnabled, SecurityHeaders)
- Observer: PascalCase, *Observer
- Command: PascalCase, *Command (LicenseIssueCommand)

Tidak ada naming violation.

## 7. Rekomendasi aksi (urutan)

1. [P1] SettingsController — pindah validate ke SystemSettingsRequest FormRequest
2. [P1] TranslationController — hapus hardcoded 'Saved.' fallback, pakai key konsisten
3. [P2] Verifikasi Payment.canceled_at & User.phone_verified_at vs migrasi
4. [P3] Ganti bcrypt() → Hash::make() di 5 file
5. [Style] Jalankan `php vendor/bin/pint` untuk auto-fix 15 file
6. [Konvensi] Diskusikan declare(strict_types=1) sebagai tim-wide policy

## 8. Apa yang dilewati (Ponytail)

- Permission model: `featureOf()` punya `return $prefix;` di akhir sebagai fallback
  ketika tidak match flag → mengembalikan prefix yang sama (bisa jadi 'user' ketika
  expected plural 'users'). Ini *by design* (komentar menjelaskan singular→plural
  mapping), bukan bug. Biarkan.
- PlanService::projectsLeft() hardcode `max(0, $max - 0)` — jelas placeholder
  untuk Model 2 tenant scoping, sudah dikomentari. Ponytail OK.
- BulkDeleteService: `auth()->user()->can()` di dalam service loop — teknis
  coupling tapi konsisten pattern. Biarkan sampai refactor ke policy class.

## Kesimpulan

Kode custom cukup mudah diikuti dan konsisten dengan dokumen tim. Pelanggaran
utama hanya 2 (inline validate + hardcoded EN fallback) yang jelas, plus style
linting yang bisa auto-fix. Tidak ada halangan besar untuk maintenance.
