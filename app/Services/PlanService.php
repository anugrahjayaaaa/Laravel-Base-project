<?php

namespace App\Services;

use App\Models\License;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;

/**
 * Entitlement gate. Single point every access/limit check routes through
 * (doc §4). Reads the SNAPSHOT from the active license, not the live plans
 * row (catalog versioning, §10.8) — so a mid-term plan change never narrows a
 * paying client's entitlement.
 *
 * Seam: for($scope) — Model 1 $scope=null (instance), Model 2 $scope=Tenant.
 */
final class PlanService
{
    private Plan $plan;

    private ?License $license;

    public function __construct(Plan $plan, ?License $license)
    {
        $this->plan = $plan;
        $this->license = $license;
    }

    /** Model 1: $scope = null. Model 2: $scope = Tenant.
     *  For per_user mode, pass $user as second arg or first — both work. */
    public static function for(?object $scope = null, ?User $user = null): self
    {
        // Auto-detect: if $scope is a User and $user wasn't passed separately,
        // treat $scope as the per-user User. This fixes production callers that
        // use PlanService::for($user) instead of PlanService::for(null, $user).
        if ($user === null && $scope instanceof User) {
            $user = $scope;
            $scope = null;
        }

        $mode = Setting::get('license_mode', 'global');

        // Per-user mode: resolve plan from user's own active license
        if ($mode === 'per_user' && $user) {
            $license = $user->license()->first();
            $slug = $license
                ? $license->getAttribute('plan_slug')
                : Setting::get('default_plan', 'free');
            $plan = Plan::where('slug', $slug)->firstOrFail();

            return new self($plan, $license instanceof License ? $license : null);
        }

        $licenseKey = Setting::get('license_key');
        $slug = $scope->plan_slug
            ?? ($licenseKey ? Setting::get('active_plan', 'free') : Setting::get('default_plan', 'free'));
        $plan = Plan::where('slug', $slug)->firstOrFail();

        // §10.1 tamper resistance: re-verify the ACTIVATED license on every
        // check. A client owning the DB could edit settings.active_plan, but
        // without a matching valid+signed license row we fall back to free.
        $license = null;
        if ($key = Setting::get('license_key')) {
            $license = License::where('license_key', $key)->first();
            if (! $license || ! LicenseService::verify($key, $license) || ! $license->isActiveAndValid()) {
                $license = null; // tampered/expired/forged -> ignore, use plan row defaults
            }
        }

        return new self($plan, $license);
    }

    /** Whether a feature is enabled for the active plan (from license snapshot). */
    public function can(string $feature): bool
    {
        // §10.1 tamper resistance: only the 'free' plan is grantable without a
        // valid activated license. Any paid plan REQUIRES a matching signed,
        // active license — a client cannot create a 'pro' plan row + flip the
        // setting to unlock features.
        if (! $this->plan->is_active) {
            return false;
        }
        if ($this->plan->slug !== 'free' && ! $this->license) {
            return false;
        }

        $features = $this->license?->snapshot['features']
            ?? $this->plan->features
            ?? [];

        return in_array($feature, $features, true);
    }

    public function membersLeft(): int
    {
        $max = $this->limit('max_members', 0);

        return max(0, $max - User::count());
    }

    private function limit(string $key, int $default): int
    {
        // paid plan without a valid license => no headroom (tamper-safe, §10.1)
        if ($this->plan->slug !== 'free' && ! $this->license) {
            return 0;
        }

        return (int) ($this->limits()[$key] ?? $default);
    }

    /** Snapshot limits from the license (or plan row), respecting tamper guard. */
    private function limits(): array
    {
        if ($this->plan->slug !== 'free' && ! $this->license) {
            return [];
        }

        return $this->license?->snapshot['limits']
            ?? $this->plan->limits
            ?? [];
    }

    /** Permission names a subscriber may assign when creating/editing roles.
     *  Empty array = no permission assigned (deny) unless explicitly listed.
     *  Role creation itself is gated by the `role.create` permission (Form
     *  Request authz), not by a separate plan flag.
     */
    public function allowedPermissions(): array
    {
        $allowed = $this->limits()['allowed_permissions'] ?? [];

        return is_array($allowed) ? $allowed : [];
    }

    /** Whether a permission is within the Plan's entitlement boundary.
     *  CHALLENGE 2: Plan acts as capability ceiling — this method provides the
     *  boundary check. Role permissions that exceed Plan entitlement become ineffective.
     *  Usage: effective permission = role_has($perm) AND Plan::for()->allows($perm).
     */
    public function allows(string $permission): bool
    {
        $allowed = $this->allowedPermissions();

        // Free plan with empty allowed_permissions = deny all (deny-by-default).
        // Non-empty allowed_permissions acts as a whitelist.
        return in_array($permission, $allowed, true);
    }

    /** The resolved Plan model. */
    public function plan(): Plan
    {
        return $this->plan;
    }

    /** No-op: permissions are derived at runtime via Role ∩ Plan, not synced to Users.
     *  Called when plan is created/updated (PlanController keeps the call site).
     *
     *  CHALLENGE 2: removed syncPermissionsForPlan's User write path — it copied
     *  Plan entitlement into model_has_permissions, violating the invariant that
     *  Plan is a capability boundary, not a permission-assignment mechanism.
     *  Runtime authorization now resolves effective permissions via PlanService::allows()
     *  (Challenge 1) + spatie role permissions; no direct User permissions exist.
     */
    public static function syncPermissionsForPlan(Plan $plan): void
    {
        // ponytail: no-op — Plan does NOT write permissions to Users.
        // Permission resolution is runtime: Role.permissions ∩ Plan.allowed_permissions.
    }
}
