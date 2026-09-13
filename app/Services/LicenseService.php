<?php

namespace App\Services;

use App\Models\License;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Issues + verifies signed license keys (Model 1: per-instance).
 *
 * One method, two callers (doc §3b):
 *  - A. auto: PG webhook success -> issue(recurring, expires_at)
 *  - B. manual: admin console/UI -> issue(lifetime|manual, expires_at=null)
 *
 * License is NON-TRANSFERABLE (doc §9b): issued_to is bound to the instance.
 */
final class LicenseService
{
    /** Build the signed key string. Includes userId for per-user uniqueness. */
    private static function sign(string $slug, ?string $expiresAt, ?int $userId = null): string
    {
        $secret = config('app.license_secret');

        // ponytail: fail closed only in prod/staging — an unset/placeholder secret makes
        // every key forgeable. Local/test run dummy mode and sign with a dev key so the
        // template works out-of-the-box.
        if (empty($secret) || $secret === 'change-me-in-production') {
            if (app()->environment(['production', 'staging'])) {
                throw new \RuntimeException('app.license_secret is not set — cannot sign license keys.');
            }
            $secret = 'dev-license-secret';
        }

        $Payload = $slug.'|'.($expiresAt ?? 'lifetime').($userId ? '|u'.$userId : '');
        $hash = substr(sha1($Payload.$secret), 0, 12);

        return 'LIC-'.Str::upper($slug).'-'.Str::upper($hash);
    }

    /**
     * Issue a license. Returns the signed key.
     */
    public static function issue(string $planSlug, array $attrs = []): string
    {
        $plan = Plan::where('slug', $planSlug)->firstOrFail();
        $expiresAt = $attrs['expires_at'] ?? null;
        $type = $attrs['type'] ?? ($expiresAt ? 'recurring' : 'manual');

        $key = self::sign($planSlug, $expiresAt, $attrs['user_id'] ?? null);

        // ponytail: idempotent — if a license with this key already exists, reactivate and return
        $existing = License::where('license_key', $key)->first();
        if ($existing) {
            $existing->update(['status' => 'active']);

            return $key;
        }

        // ponytail: snapshot plan limits/features at issue time (catalog versioning, §10.8)
        License::create([
            'plan_slug' => $planSlug,
            'user_id' => $attrs['user_id'] ?? null,
            'license_key' => $key,
            'type' => $type,
            'status' => 'active',
            'issued_to' => $attrs['issued_to'] ?? null,
            'expires_at' => $expiresAt,
            'snapshot' => ['limits' => $plan->limits, 'features' => $plan->features],
        ]);

        return $key;
    }

    /**
     * Activate a license. In global mode, writes to global settings and revokes
     * all other active licenses. In per_user mode, only the target user's license
     * is affected — no global settings change, no cross-user revocation.
     */
    public static function activate(string $key, ?string $issuedTo = null, ?User $forUser = null): bool
    {
        $license = License::where('license_key', $key)->first();

        if (! $license || ! self::verify($key, $license)) {
            return false;
        }
        if (! $license->isActiveAndValid()) {
            return false;
        }

        // Per-user activation (per_user mode with explicit user): just update the license
        // without touching global settings or revoking other users' licenses.
        if ($forUser) {
            $license->update(['issued_to' => $issuedTo ?? $forUser->email, 'status' => 'active']);
            activity()->on($license)
                ->withProperties(['plan' => $license->plan_slug, 'user_id' => $license->user_id, 'mode' => 'per_user'])
                ->log('license.activated');

            return true;
        }

        // Global mode: existing behavior — single active license per instance
        License::where('status', 'active')
            ->where('id', '!=', $license->id)
            ->update(['status' => 'expired']);

        $license->update(['issued_to' => $issuedTo, 'status' => 'active']);

        Setting::set('active_plan', $license->plan_slug);
        Setting::set('license_key', $key);

        activity()->on($license)
            ->withProperties(['plan' => $license->plan_slug, 'mode' => 'global'])
            ->log('license.activated');

        return true;
    }

    /** Verify the key's signature against the stored license. */
    public static function verify(string $key, License $license): bool
    {
        $expected = self::sign($license->plan_slug, $license->expires_at?->format('Y-m-d H:i:s'), $license->user_id);

        return hash_equals($expected, $key);
    }

    /**
     * Issue a license for a specific user (per_user mode).
     * Returns the signed key. Unlike activate(), this does NOT touch global settings.
     */
    public static function issueFor(User $user, string $planSlug, array $attrs = []): string
    {
        $attrs['user_id'] = $user->id;

        return self::issue($planSlug, $attrs);
    }

    /** Issue a default Free license for a user (per_user mode provisioning). */
    public static function defaultLicenseForUser(User $user): ?License
    {
        $freePlan = Plan::where('slug', 'free')->first();
        if (! $freePlan) {
            return null;
        }

        // ponytail: don't duplicate — use firstOrCreate on user_id + plan_slug + active
        // Key includes user_id via sign() for uniqueness — consistent key format
        return License::firstOrCreate(
            ['user_id' => $user->id, 'plan_slug' => 'free', 'status' => 'active'],
            [
                'license_key' => self::sign('free', null, $user->id),
                'type' => 'manual',
                'status' => 'active',
                'expires_at' => null,
                'issued_to' => null,
                'snapshot' => ['limits' => $freePlan->limits, 'features' => $freePlan->features],
            ]
        );
    }

    /** Status of the current license (or 'none'). Accepts optional User for per_user mode. */
    public static function status(?User $user = null): string
    {
        $license = self::resolveActiveLicense($user);
        if (! $license) {
            return 'none';
        }
        if ($license->status === 'revoked') {
            return 'revoked';
        }
        if ($license->expires_at && $license->expires_at->isPast()) {
            return 'expired';
        }

        return 'active';
    }

    /** Days left on the current license (null = lifetime/INF). Accepts optional User for per_user mode. */
    public static function daysLeft(?User $user = null): ?int
    {
        $license = self::resolveActiveLicense($user);
        if (! $license || ! $license->expires_at) {
            return null;
        }

        // Signed diff: positive if future, negative if expired.
        return (int) now()->startOfDay()->diffInDays($license->expires_at->startOfDay());
    }

    /** Resolve the current license for status/display — global or per-user.
     *  Unlike PlanService, this returns the most recent license regardless
     *  of expiration, so the dashboard can show 'expired' status.
     */
    private static function resolveActiveLicense(?User $user = null): ?License
    {
        $mode = Setting::get('license_mode', 'global');

        // Per-user mode: return the user's most recent license (regardless of expiry)
        if ($mode === 'per_user' && $user) {
            return $user->licenses()
                ->orderBy('id', 'desc')
                ->first();
        }

        // Global mode: check the instance-level activated license
        return self::activeLicense();
    }

    /**
     * Shared lookup of the currently activated license row (if any).
     * Reads the key from settings once; used by status(), daysLeft(),
     * and DashboardController to avoid duplicate settings+license queries.
     */
    public static function activeLicense(): ?License
    {
        $key = Setting::get('license_key');
        if (! $key) {
            return null;
        }

        return License::where('license_key', $key)->first();
    }

    /** Revoke a license (abuse / manual). Instant lock (doc §10.7). */
    public static function revoke(string $key, ?string $reason = null): void
    {
        $license = License::where('license_key', $key)->first();
        if (! $license) {
            return;
        }
        $license->update(['status' => 'revoked', 'revoke_reason' => $reason]);
        if (Setting::get('license_key') === $key) {
            Setting::set('active_plan', 'free');
            Setting::set('license_key', null);
        }
        activity()->on($license)
            ->withProperties(['plan' => $license->plan_slug, 'reason' => $reason])
            ->log('license.revoked');
    }
}
