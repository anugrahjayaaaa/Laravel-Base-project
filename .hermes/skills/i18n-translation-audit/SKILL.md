# Laravel i18n translation-group audit (ui vs messages)

Use when: adding, moving, or auditing translation keys in a Laravel-Base-Project
fork. Maintains the invariant that a key lives in exactly one lang file
(`ui.php` = interface chrome via `ui()`; `messages.php` = domain/content via
`__('messages.*')`).

## Before editing views or lang files

1. Read `docs/i18n.md` first.
2. Read the two lang files you will touch (`lang/en/{ui,messages}.php`). Do not
   assume — grep for the key in both files before adding it.

## Key placement rule (root cause)

- Interface chrome (buttons, nav labels, headings, table headers, modals,
  breadcrumbs, toast text) → `lang/{locale}/ui.php`, called via `ui('key')`.
- Domain/API/flash content (error/validation messages, JSON responses, email
  text, per-feature help, descriptions) → `lang/{locale}/messages.php`, called
  via `__('messages.key')`.
- A key must exist in **exactly one** file. If a label could be either, prefer
  `ui.php` (chrome wins; same DB table, same override path).

## Audit script (run from repo root)

```bash
php -r '
$u = require "lang/en/ui.php";
$m = require "lang/en/messages.php";

// 1. cross-file duplicates (key in BOTH ui and messages)
$cross = array_intersect(array_keys($u), array_keys($m));
echo "cross-dupes: ", $cross ? implode(",", $cross) : "none", "\n";

// 2. within-file duplicates
foreach (["ui.php"=>$u, "messages.php"=>$m] as $f=>$a) {
  $dupes = array_keys(array_filter(array_count_values(array_keys($a)), fn($c)=>$c>1));
  echo "dupes $f: ", $dupes ? implode(",", $dupes) : "none", "\n";
}

// 3. every ui() / __("messages.X") call in views resolves
$miss = [];
exec("grep -rhoE \"(__|ui)\(\x27[^\x27]+\x27\" resources/views/ 2>/dev/null", $lines);
foreach ($lines as $l) {
  if (preg_match("/ui\(\x27([^\x27]+)\x27/", $l, $mt)) {
    $k=$mt[1];
    if (preg_match("/^(feature_group_|period_)/", $k)) continue;  // dynamic concat
    if (!array_key_exists($k, $u)) $miss[] = "ui: $k";
  }
  if (preg_match("/__\(\x27messages\.([^\\x27]+)\x27/", $l, $mt)) {
    if (!array_key_exists($mt[1], $m)) $miss[] = "msg: $mt[1]";
  }
}
echo "missing keys: ", $miss ? implode(",", array_values(array_unique($miss))) : "none", "\n";
'
```

## When adding a key

1. Pick the correct group (`ui` or `messages`) per the rule above.
2. Add to **both** `lang/en/` and `lang/id/` file.
3. Update every view call site to use the matching helper
   (`ui('key')` or `__('messages.key')`).
4. Re-seed so the DB mirrors the files:
   `php artisan db:seed --class=LanguageLineSeeder --force`
   (`LanguageLineSeeder` is idempotent — inserts/updates and **deletes**
   rows whose key no longer exists in the lang files, so it keeps the
   `language_lines` table in lockstep with the source files.)
5. The spatie loader resolves DB → falls back to file; no app restart needed.

## Gotchas

- `ui()` uses group `ui`; `__('X')` without a group is NOT the same — always
  pass the full group: `ui('save')` or `__('messages.save')`, never bare
  `__('save')`.
- Dynamic concat keys like `ui('feature_group_'.$group)` and
  `ui('period_'.$x)` must have every concrete variant present
  (`feature_group_access`, `period_monthly`, …).
- Editing `language_lines` table directly in DB is fine for runtime overrides,
  but the **file is the source of truth** — the seeder will overwrite DB-only
  keys on the next run.

## Fallback traps (i18n null bug hazard)

- **Never hardcode an English fallback after a translation call.**
  ` __('messages.saved') ?? 'Saved.'`  /  `ui('submit') ?? 'Save'`
  is a silent bug. Laravel's translator returns the *key string* (not null)
  on a missing key in most paths, so the `??` branch rarely fires — but
  spatie TranslationLoader can return null in edge cases, leaking the literal
  English into the UI. Remove every `?? '...'` fallback; if the key is missing,
  **add the key** to both `lang/en` and `lang/id` (and re-seed). The fallback
  should be the key itself, never prose.
- **Backend flash-status hazard.** A controller that flashes a status via
  `__('messages.x') ?? '...'` (instead of `ui('x')` for page chrome) silently
  mixes the two groups. If the key is only in `ui.php`, `__('messages.x')`
  returns the literal key string — fix the call site, don't fallback around it.
  Audit sweep (controller + views):
  ```bash
  grep -rn "?? '\|?? \"" app/Http/Controllers/ resources/views/
  ```
- **Root fix > workaround.** When a key lookup returns null/key-string at runtime,
  the fix is to define the key in the correct lang file + re-seed
  `language_lines`, not to paper over it with a fallback that hides the bug.
