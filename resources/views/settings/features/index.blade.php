@extends('layouts.app')
@section('content')
@include('partials.flash-message')
<h3>{{ ui('feature_flags') }}</h3>
<p class="text-muted">{{ ui('feature_flags_intro', ['code' => '<code>feature.manage</code>']) }}</p>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>{{ ui('feature') }}</th><th>{{ ui('slug') }}</th><th>{{ ui('status') }}</th><th class="text-end">{{ ui('action') }}</th></tr>
            </thead>
            <tbody>
                @forelse ($features as $group => $items)
                <tr class="table-group-divider">
                    <th colspan="4" class="text-uppercase small text-muted fw-bold">{{ ui('feature_group_'.$group) }}</th>
                </tr>
                @foreach ($items as $feature)
                <tr>
                    <td>{{ $feature['label'] }}</td>
                    <td><span class="text-muted small">{{ $feature['slug'] }}</span></td>
                    <td>
                        @if($feature['enabled'])
                            <span class="badge text-bg-success">{{ ui('enabled') }}</span>
                        @else
                            <span class="badge text-bg-secondary">{{ ui('disabled') }}</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="form-check form-switch d-inline-flex align-items-center justify-content-end mb-0">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="feat-{{ $feature['slug'] }}" {{ $feature['enabled'] ? 'checked' : '' }}
                                   aria-label="{{ ui('change_status_of') . ': ' . $feature['label'] }}"
                                   data-bs-toggle="modal" data-bs-target="#featureToggleModal"
                                   data-action="{{ route('features.toggle', $feature['slug']) }}"
                                   data-enabled="{{ $feature['enabled'] ? '0' : '1' }}">
                        </div>
                    </td>
                </tr>
                @endforeach
                @empty
                <tr><td colspan="4" class="text-center text-muted py-4">{{ ui('no_features') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>

@include('partials.modals.feature-toggle-modal')
@endsection
