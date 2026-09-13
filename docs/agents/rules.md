---
id: AGENT-RULES
name: AI Agent Coding Rules
type: agents
status: locked
---

# AI Agent Rules — Coding & Conventions

> Mandatory checklist for any code change. Violations fail review.
> Source: `docs/base/conventions/coding.md` (canonical).

## 1. Authorization — gate on the ROUTE

- Permission + feature-flag gates belong on the route definition (`routes/web.php`).
- Do NOT call `$this->middleware(...)` inside a controller `__construct()`.
- Every protected route MUST carry `can:{perm}`. Module routes also carry
  `feature:{slug}`.

```php
// ✅ correct
Route::resource('users', UserController::class)
    ->middleware(['can:user.view', 'feature:users']);
// ❌ wrong — caused PlanController crash (undefined method middleware())
class PlanController extends Controller {
    public function __construct() {
        $this->middleware('can:feature.manage');  // base Controller had no helper
    }
}
```

## 2. Base Controller

- `app/Http/Controllers/Controller` MUST extend `Illuminate\Routing\Controller`.
  (This provides `middleware()`, `validate()`, `authorize()`.)

## 3. Validation — Form Request, never inline

- No `$request->validate()` inside controllers.
- Use `app/Http/Requests/<Domain>/<Action><Resource>Request.php` extending
  `FormRequest`.
- `authorize()` returns the permission gate (not `true`) for protected routes.
- Group requests by domain folder: `Auth/`, `User/`, `Rbac/`, `Profile/`, etc.

## 4. Controllers stay thin

- Validate (Form Request) → call Service/Action → return view/redirect/response.
- Business logic → `app/Services/*` or action classes.
- Models use `SoftDeletes`; mutations logged via observer/activitylog.

## 5. Feature flags

- Declared in `config/pennant.php`, defined in `AppServiceProvider::boot()`.
- Enforced via `feature:{slug}` middleware + `@feature()` Blade directive.
- Flag off → route 404 + sidebar item hides.

## 6. Naming & style

- PSR-12, `declare(strict_types=1)`, type hints required.
- `snake_case` DB, `camelCase` JS, `PascalCase` class, `kebab-case` route/permission slugs.
- Permission slugs: `resource.action` (e.g. `user.view`, `role.delete`).

## 7. Definition of Done (per PR)

- [ ] Route carries `can:` (+ `feature:` if module-flagged)
- [ ] Validation in a Form Request
- [ ] Controller thin; logic in service/action
- [ ] Pest tests green (`php -d memory_limit=2G ./vendor/bin/pest`)
- [ ] PHPStan clean (`vendor/bin/phpstan analyse`)
- [ ] Pint clean (`vendor/bin/pint`)
- [ ] Docs match code (update ADR / module doc if behavior changed)
- [ ] No secrets; security checklist green

## 8. Guardrails

- Base `Controller` extends framework — never revert to empty abstract.
- Authorization is route-level; move any `$this->middleware(` to `routes/web.php`.
- Every new module: route + middleware + thin controller + Form Request + doc.

## i18n

- `ui()` calls keys from `ui.php`; `__('messages.*')` from `messages.php`.
- Never mix the two in one call. Never hardcode English in Blade (`?? 'fallback'`).
- New string → add to `lang/en/ui.php` + `lang/id/ui.php`, re-seed LanguageLineSeeder.

## Test references

See [`docs/agents/test-refs.md`](./test-refs.md) for the browser-based test
matrix keyed by URL + permission + feature flag.
