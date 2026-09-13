# Coding Standard (mandatory)

Read before writing any code. Violations fail review. The goal: one pattern
per concern so incidents like the PlanController middleware crash cannot recur.

## 1. Authorization — gate on the ROUTE, never in the controller
**Rule:** permission + feature-flag gates belong on the route definition
(`routes/web.php`), using Laravel's built-in middleware. Do **not** call
`$this->middleware(...)` inside a controller `__construct()`.

```php
// ✅ correct — repo convention
Route::resource('plans', PlanController::class)
    ->middleware(['can:feature.manage']);          // permission
// (or stacked with the module flag if plans becomes a feature-flagged module)

// ❌ wrong — caused a fatal: base Controller had no middleware() helper
class PlanController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:feature.manage');   // undefined method
    }
}
```

Why: the gate is declarative, visible in one file, and does not depend on the
controller extending a specific base. It also composes with feature flags
(`feature:{slug}`) in the same `middleware([...])` array.

- **Every protected resource route MUST carry `can:{perm}`.** Module routes also
  carry `feature:{slug}` (stacked BEFORE `can:` so Pennant's 404 wins). Reference: `routes/web.php`, `docs/base/features/rbac.md`,
  `docs/base/features/feature-flags.md`.
- **Super-admin bypass**: `User::isSuperAdmin()` (has the `super-admin` Role, not username-based)
  bypasses Plan entitlement in the Gate `before` callback. Pennant is NOT bypassed — the global
  kill switch applies to super-admin too. See ADR-0011.

## 2. Base Controller
`app/Http/Controllers/Controller` MUST extend `Illuminate\Routing\Controller`.
```php
abstract class Controller extends \Illuminate\Routing\Controller {}
```
This provides `middleware()`, `validate()`, `authorize()` for any controller
that legitimately needs them (e.g. inline `authorize()` in a Form Request is
preferred — see §3). Do not leave it empty; that reintroduces the
undefined-method class of bugs.

## 3. Validation — Form Request, never inline
- No `$request->validate()` inside controllers. Use
  `app/Http/Requests/<Domain>/<Action><Resource>Request.php` extending
  `FormRequest`.
- `authorize()` returns the permission gate (e.g. `$this->user()->can('user.create')`),
  NOT `true` for protected resources. Public endpoints (login, forgot/reset)
  may return `true`.
- Controller type-hints the Form Request and calls `$request->validated()`.
- Group requests by domain folder: `Auth/`, `User/`, `Rbac/`, `Profile/`,
  `Api/`, `Session/`, `Translation/`.

## 4. Controllers stay thin
- Only: validate (via Form Request), call a Service/Action, return a view/redirect/response.
- Business logic → `app/Services/*` or action classes.
- Models use `SoftDeletes`; mutations logged via observer/activitylog.

## 5. Feature flags
- Declared in `config/pennant.php`, defined in `AppServiceProvider::boot()`.
- Enforced on routes via `feature:{slug}` middleware + `@feature()` in Blade.
- A flag off 404s the route and hides its sidebar item for everyone.

## 6. Naming & style
- PSR-12, `declare(strict_types=1)`, type hints required.
- `snake_case` DB, `camelCase` JS, `PascalCase` class, `kebab-case` route/permission slugs.
- Permission slugs: `resource.action` (`user.view`, `role.delete`, `feature.manage`).

## 7. Definition of Done (per PR)
- [ ] Route carries `can:` (+ `feature:` if module-flagged); no constructor gate.
- [ ] Validation in a Form Request; `authorize()` non-trivial for protected routes.
- [ ] Controller thin; logic in service/action.
- [ ] Pest tests green (`php -d memory_limit=2G ./vendor/bin/pest`).
- [ ] Docs match code (update ADR / module doc if behavior changed).
- [ ] No secrets; security checklist green.

## 8. Guardrails (prevent regressions)
- Base `Controller` extends framework — never revert to empty abstract.
- Authorization is route-level; if you see `$this->middleware(` in a
  controller, move it to `routes/web.php`.
- Every new module = route + middleware + thin controller + Form Request + doc.
