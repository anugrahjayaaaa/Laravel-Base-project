# Observability (Logger & Monitoring)

Goal: fast root cause when an issue/bug happens in prod.

## Logs (structured)
- `Log::info/error` with context array (not string concat).
- `daily` channel in prod; never log secrets/PII.
- Exception handler → log + Sentry (stack + release + user tag).
- **Where to look:**
  - File: `storage/logs/laravel-YYYY-MM-DD.log` (rotated daily, `LOG_STACK=daily`).
  - Sentry: Issues stream (if `SENTRY_DSN` set) — tagged `user_id`, `release`.
  - `/up` for health; `laravel-<date>.log` for everything else.
- **4xx client errors (except 404) are logged automatically** via `LogHttpErrors`
  middleware: 405/403/422 etc. land in the daily log with url/method/ip/user_id.
  (404 skipped to avoid noise from missing assets/crawlers.) 5xx already logged by
  Laravel's exception handler.

## Implementation Reference (functions)

### `App\Http\Middleware\LogHttpErrors::handle(Request $request, Closure $next)`
- Purpose: capture response, log 4xx client errors (except 404) for monitoring.
- Input: `$request` (HTTP request), `$next` (next middleware).
- Output: unchanged `Response` (pass-through).
- State / where:
  - Logs to **daily log channel** → file `storage/logs/laravel-YYYY-MM-DD.log`
    (driver `config('logging.default')`, default `daily`).
  - Context: `url`, `method`, `ip`, `user_id`.
  - 5xx already handled by Laravel exception handler; 404 skipped (noise).
- Registered globally in `bootstrap/app.php`.

## Error tracking — Sentry
- Init in bootstrap; `release = app.version`; small `traces_sample_rate` (0.2).
- Tag `user_id` to filter per user.
- Alerts: error-rate spike, /up failing, queue backlog.

## Health check
- `/up` route (Laravel health) checks DB + cache + queue. Used by LB/uptime.

## DB monitoring
- Slow query log + performance_schema (see `mysql` skill).
- Alert when p95 exceeds threshold.

## Gate
- Test exception in staging lands in Sentry with release tag.
- /up returns 200 + DB/cache checks green.
- Logs do not leak secrets.
