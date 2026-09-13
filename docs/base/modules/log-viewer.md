# Web Log Viewer

Package: `rap2hpoutre/laravel-log-viewer` (popular, safe — no raw file access, only reads configured log paths).

## Usage
- Route: `/logs` (gated by the `logs.view` permission; only roles with that permission can open it).
- Shows `storage/logs/*.log`, filterable by level (error, warning, etc.) and date.

## Why this package
- Avoids hand-rolling a file reader (path traversal risk). The package whitelists log directories.
- No extra config needed beyond the permission gate.

## Regenerating docs
Scribe docs (`/docs`) are generated from route annotations; re-run `php artisan scribe:generate` after
changing API responses. `.scribe/` cache is git-ignored.
