@php
use App\Models\User;
use App\Services\LicenseService;
@endphp
@extends('layouts.app')
@section('content')
<div class="row g-3 mb-3">
    <div class="col-12 col-sm-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="rounded-circle d-flex align-items-center justify-content-center text-bg-primary" style="width:48px;height:48px"><i class="bi bi-people fs-4"></i></span>
                <div><div class="text-muted small">{{ ui('users') }}</div><div class="fs-4 fw-semibold">{{ $userCount ?? 0 }}</div></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="rounded-circle d-flex align-items-center justify-content-center text-bg-success" style="width:48px;height:48px"><i class="bi bi-shield fs-4"></i></span>
                <div><div class="text-muted small">{{ ui('roles') }}</div><div class="fs-4 fw-semibold">{{ $roleCount ?? 0 }}</div></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="rounded-circle d-flex align-items-center justify-content-center text-bg-warning" style="width:48px;height:48px"><i class="bi bi-journal-text fs-4"></i></span>
                <div><div class="text-muted small">{{ ui('audit_entries') }}</div><div class="fs-4 fw-semibold">{{ $auditCount ?? 0 }}</div></div>
            </div>
        </div>
    </div>
</div>

{{-- License status badge (REQUIRED, doc §9b) --}}
@php
    $licStatus = $licenseStatus ?? 'none';
    $licBadge = match ($licStatus) {
        'active' => 'text-bg-success',
        'expired', 'revoked' => 'text-bg-danger',
        'none' => 'text-bg-secondary',
        default => 'text-bg-warning',
    };
    $licText = match ($licStatus) {
        'active' => $licenseDaysLeft === null
            ? ui('license_active_lifetime')
            : ui('license_active_days', ['days' => $licenseDaysLeft]),
        'expired'    => ui('license_expired'),
        'revoked'    => ui('license_revoked'),
        'none'       => ui('license_none'),
        default      => ui('license_status', ['status' => $licStatus]),
    };
@endphp
<div class="mb-3">
    <span class="badge {{ $licBadge }} fs-6">
        <i class="bi bi-patch-check me-1"></i>{{ $licText }}
        <span class="opacity-75 ms-1">({{ $activePlan ?? ui('free_plan') }})</span>
    </span>
</div>

{{-- 7-day expiration warning (per_user mode) --}}
@unless($user->isSuperAdmin())
@php
    $warningLicense = $license ?? LicenseService::activeLicense();
@endphp
@if($warningLicense && $warningLicense->expires_at && $warningLicense->expires_at->isFuture() && $warningLicense->expires_at->lte(now()->addDays(7)))
<div class="alert alert-warning mb-3" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i>
    {{ ui('license_expiring_warning', [
        'days' => max(0, (int) now()->diffInDays($warningLicense->expires_at)),
        'plan' => $warningLicense->plan_slug,
        'date' => $warningLicense->expires_at->format('M d, Y'),
    ]) }}
</div>
@endif
@endunless

<div class="card shadow-sm border-0">
    <div class="card-body">
        <h5 class="mb-1">{{ ui('welcome', ['name' => auth()->user()->name]) }} 👋</h5>
        <p class="text-muted mb-0">{{ ui('dashboard_subtitle') }}</p>
    </div>
</div>
@endsection
