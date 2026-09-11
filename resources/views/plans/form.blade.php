@extends('layouts.app')
@section('content')
@php
    $title = $plan ? ui('edit_plan') : ui('new_plan');
    $licenseMode = \App\Models\Setting::get('license_mode', 'global');
@endphp
@include('partials.flash-message')
<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ $plan ? route('plans.update', $plan) : route('plans.store') }}">
            @csrf
            @if($plan) @method('PUT') @endif

            {{-- General --}}
            <h6 class="text-uppercase text-muted small fw-bold mb-3">{{ ui('common_info') }}</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">{{ ui('name') }}</label>
                    <input type="text" name="name" id="plan-name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $plan->name ?? '') }}" required aria-describedby="name-error" @error('name') aria-invalid="true" @enderror>
                    @error('name')<div id="name-error" class="invalid-feedback d-block w-100" role="alert" aria-live="polite">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ ui('slug') }}</label>
                    <input type="text" name="slug" id="plan-slug" class="form-control @error('slug') is-invalid @enderror"
                           value="{{ old('slug', $plan->slug ?? '') }}"
                           placeholder="{{ ui('slug_auto') }}" {{ $plan && $plan->slug === 'free' ? 'readonly' : '' }} aria-describedby="slug-error" @error('slug') aria-invalid="true" @enderror>
                    <div class="form-text">{{ ui('slug_help') }}</div>
                    @error('slug')<div id="slug-error" class="invalid-feedback d-block w-100" role="alert" aria-live="polite">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ ui('price_monthly') }}</label>
                    <input type="number" step="0.01" min="0" name="price_monthly" class="form-control @error('price_monthly') is-invalid @enderror"
                           value="{{ old('price_monthly', $plan->price_monthly ?? 0) }}" required aria-describedby="price_monthly-error" @error('price_monthly') aria-invalid="true" @enderror>
                    @error('price_monthly')<div id="price_monthly-error" class="invalid-feedback d-block w-100" role="alert" aria-live="polite">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ ui('billing_period') }}</label>
                    <select name="billing_period" id="billing_period" class="form-select @error('billing_period') is-invalid @enderror" aria-describedby="billing_period-error" @error('billing_period') aria-invalid="true" @enderror>
                        <option value="monthly" @selected(old('billing_period', $plan->billing_period ?? 'monthly') === 'monthly')>{{ ui('period_monthly') }}</option>
                        <option value="lifetime" @selected(old('billing_period', $plan->billing_period ?? 'monthly') === 'lifetime')>{{ ui('period_lifetime') }}</option>
                    </select>
                    @error('billing_period')<div id="billing_period-error" class="invalid-feedback d-block w-100" role="alert" aria-live="polite">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="is_active" value="1"
                               {{ old('is_active', $plan->is_active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">{{ ui('active') }}</label>
                    </div>
                </div>
            </div>

            {{-- Capacity (global mode shows all; per-user shows only max_permissions + max_features) --}}
            @php($licenseMode = \App\Models\Setting::get('license_mode', 'global'))
            <h6 class="text-uppercase text-muted small fw-bold mb-3">{{ ui('capacity_limits') }}</h6>
            <div class="row g-3 mb-1">
                @if ($licenseMode === 'global')
                <div class="col-md-4 col-lg-2">
                    <label class="form-label">{{ ui('limit_max_members') }}</label>
                    <input type="number" min="0" name="max_members" class="form-control"
                           value="{{ old('max_members', $plan?->limit('max_members') ?? 0) }}">
                </div>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label">{{ ui('limit_max_storage_mb') }}</label>
                    <input type="number" min="0" name="max_storage_mb" class="form-control"
                           value="{{ old('max_storage_mb', $plan?->limit('max_storage_mb') ?? 0) }}">
                </div>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label">{{ ui('limit_max_roles') }}</label>
                    <input type="number" min="0" name="max_roles" class="form-control"
                           value="{{ old('max_roles', $plan?->limit('max_roles') ?? 0) }}">
                </div>
                @endif
                <div class="col-md-4 col-lg-2">
                    <label class="form-label">{{ ui('limit_max_permissions') }}</label>
                    <input type="number" min="0" name="max_permissions" class="form-control"
                           value="{{ old('max_permissions', $plan?->limit('max_permissions') ?? 0) }}">
                </div>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label">{{ ui('limit_max_features') }}</label>
                    <input type="number" min="0" name="max_features" id="max_features" class="form-control"
                           value="{{ old('max_features', $plan?->limit('max_features') ?? 0) }}">
                </div>
                @if ($licenseMode === 'global')
                <div class="col-md-4 col-lg-2 d-flex align-items-end">
                    <small class="text-muted">{{ ui('roles_auto_create') }}</small>
                </div>
                @endif
            </div>

            {{-- Features --}}
            @if (\App\Models\Setting::get('license_mode', 'global') === 'global')
            <h6 class="text-uppercase text-muted small fw-bold mt-4 mb-2 d-flex justify-content-between">
                <span>{{ ui('features') }}</span>
                <span class="badge bg-secondary" id="feature-counter">0</span>
            </h6>
            @else
            <h6 class="text-uppercase text-muted small fw-bold mb-2">{{ ui('features') }}</h6>
            @endif
            <div class="row row-cols-2 row-cols-md-3 g-2 mb-4" id="feature-list">
                @foreach(config('pennant.features') as $slug => $cfg)
                    @php($checked = in_array($slug, old('features', $plan->features ?? [])))
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input feat-toggle" type="checkbox" name="features[]"
                                   value="{{ $slug }}" id="feat-{{ $slug }}" @checked($checked)
                                   data-feature="{{ $slug }}">
                            <label class="form-check-label" for="feat-{{ $slug }}">{{ $cfg['label'] ?? $slug }}</label>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Permissions (grouped by feature, filtered by enabled features) --}}
            <h6 class="text-uppercase text-muted small fw-bold mb-2">{{ ui('allowed_permissions') }}</h6>
            <div class="form-text mb-3">{{ ui('allowed_permissions_help') }}</div>
            <div class="accordion" id="perm-accordion">
                @php($grouped = $permissions->groupBy(fn ($p) => App\Models\Permission::featureOf($p)))
                @foreach(config('pennant.features') as $slug => $cfg)
                    <div class="accordion-item perm-group" data-feature="{{ $slug }}">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#perm-{{ $slug }}" aria-expanded="false">
                                {{ $cfg['label'] ?? $slug }}
                            </button>
                        </h2>
                        <div id="perm-{{ $slug }}" class="accordion-collapse collapse" data-bs-parent="#perm-accordion">
                            <div class="accordion-body row row-cols-2 row-cols-md-3 g-2">
                                @foreach($grouped->get($slug, []) as $perm)
                                    @php($checked = in_array($perm, old('allowed_permissions', $plan->limits['allowed_permissions'] ?? [])))
                                    <div class="col">
                                        <div class="form-check">
                                            <input class="form-check-input perm-toggle" type="checkbox" name="allowed_permissions[]"
                                                   value="{{ $perm }}" id="perm-{{ Str::slug($perm) }}" @checked($checked)>
                                            <label class="form-check-label" for="perm-{{ Str::slug($perm) }}">{{ $perm }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="card-footer d-flex justify-content-end gap-2">
                <a href="{{ route('plans.index') }}" class="btn btn-light border">{{ ui('cancel') }}</a>
                <button class="btn btn-primary">{{ ui('save') }}</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    // Slug auto-generates from name; user can override until they type in slug.
    (() => {
        const name = document.getElementById('plan-name');
        const slug = document.getElementById('plan-slug');
        if (name && slug && !slug.readOnly) {
            let touched = slug.value.trim() !== '';
            slug.addEventListener('input', () => { touched = true; });
            name.addEventListener('input', () => {
                if (!touched) slug.value = name.value.toLowerCase().trim()
                    .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
            });
        }
    })();

// Feature + permission checkboxes: respect max_features and max_permissions caps.
(() => {
    const maxFeatInput = document.getElementById('max_features');
    const maxPermInput = document.getElementById('max_permissions');
    const toggles = Array.from(document.querySelectorAll('.feat-toggle'));
    const counter = document.getElementById('feature-counter');
    const groups = Array.from(document.querySelectorAll('.perm-group'));
    const permToggles = Array.from(document.querySelectorAll('.perm-toggle')); // class-based, reliable

    const apply = () => {
        const on = toggles.filter(t => t.checked).map(t => t.dataset.feature);
        const maxFeat = parseInt(maxFeatInput?.value || '0', 10);
        const maxPerm = parseInt(maxPermInput?.value || '0', 10);

        // Feature toggles: max 0 → disable all
        if (maxFeat > 0) {
            toggles.forEach(t => {
                if (!t.checked && on.length >= maxFeat) t.disabled = true;
                else t.disabled = false;
            });
        } else {
            toggles.forEach(t => t.disabled = true);
        }

        if (counter) counter.textContent = on.length + (maxFeat > 0 ? ' / ' + maxFeat : '');

        // Permission toggles: only apply caps when max_permissions input exists (global mode)
        if (maxPermInput && permToggles.length > 0) {
            const visiblePerms = permToggles.filter(p => {
                const group = p.closest('.perm-group');
                return group && group.style.display !== 'none';
            });
            const checkedPerms = visiblePerms.filter(p => p.checked).length;

            if (maxPerm > 0) {
                permToggles.forEach(p => {
                    if (!p.checked && checkedPerms >= maxPerm) p.disabled = true;
                    else p.disabled = false;
                });
            } else {
                permToggles.forEach(p => p.disabled = true);
            }
        }

        const set = new Set(on);
        groups.forEach(g => {
            g.style.display = set.has(g.dataset.feature) ? '' : 'none';
        });
    };
    toggles.forEach(t => t.addEventListener('change', apply));
    permToggles.forEach(p => p.addEventListener('change', apply));
    maxFeatInput?.addEventListener('input', apply);
    maxPermInput?.addEventListener('input', apply);
    apply();
})();
</script>
@endpush
@endsection
