@extends('layouts.app')
@section('content')
@include('partials.flash-message')
<h3>{{ ui('notifications') }}</h3>
<p class="text-muted">{{ ui('notifications_intro') }}</p>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            @forelse ($notifications as $n)
                <div class="list-group-item py-3 {{ $n->read_at ? '' : 'list-group-item-primary' }}">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="min-w-0">
                            <div class="fw-semibold text-break">
                                <i class="bi bi-activity text-primary me-1"></i>{{ $n->data['label'] ?? ($n->data['action'] ?? 'Notification') }}
                            </div>
                            <div class="text-muted small mt-1">
                                @if (!empty($n->data['ip']))
                                    <span class="me-2"><i class="bi bi-geo-alt"></i> {{ $n->data['ip'] }}</span>
                                @endif
                                @if ($n->read_at)
                                    <span>{{ ui('read') }} {{ $n->read_at->diffForHumans() }}</span>
                                @else
                                    <span class="text-primary fw-medium">{{ ui('unread') }}</span>
                                @endif
                            </div>
                        </div>
                        <span class="text-muted small text-nowrap ms-2">{{ $n->created_at->diffForHumans() }}</span>
                    </div>
                </div>
            @empty
                <div class="list-group-item py-4 text-center text-muted">{{ ui('no_notifications_yet') }}</div>
            @endforelse
        </div>
    </div>
</div>

@if ($notifications->hasPages())
    <div class="mt-3">
        {{ $notifications->links() }}
    </div>
@endif
@endsection
