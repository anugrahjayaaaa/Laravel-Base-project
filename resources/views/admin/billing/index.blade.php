@extends('layouts.app')
@section('content')
@include('partials.flash-message')
<h3>{{ ui('billing_admin') }}</h3>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <div class="text-muted small">{{ __('messages.revenue') }}</div>
                <div class="h4">Rp {{ number_format($revenue, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <div class="text-muted small">{{ __('messages.active_subscribers') }}</div>
                <div class="h4">{{ $activeSubscribers }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <div class="text-muted small">{{ __('messages.paid_this_month') }}</div>
                <div class="h4">{{ $paidThisMonth }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <div class="text-muted small">{{ ui('plans') }}</div>
                <div class="h4">{{ $planBreakdown->count() }}</div>
            </div>
        </div>
    </div>
</div>

<h5>{{ __('messages.plan_breakdown') }}</h5>
<div class="card shadow-sm mb-4">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>{{ ui('plan') }}</th><th>{{ __('messages.active_licenses') }}</th></tr></thead>
            <tbody>
                @forelse($planBreakdown as $slug => $total)
                    <tr><td>{{ $slug }}</td><td>{{ $total }}</td></tr>
                @empty
                    <tr><td colspan="2" class="text-muted text-center">{{ __('messages.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<h5>{{ __('messages.recent_payments') }}</h5>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>{{ ui('invoice') }}</th><th>{{ ui('user') }}</th><th>{{ ui('plan') }}</th><th>{{ ui('amount') }}</th><th>{{ ui('status') }}</th><th>{{ ui('date') }}</th></tr></thead>
            <tbody>
                @forelse($payments as $p)
                    <tr>
                        <td><code>{{ $p->invoice_no }}</code></td>
                        <td>{{ $p->user?->name ?? '-' }}</td>
                        <td>{{ $p->plan_slug }}</td>
                        <td>Rp {{ number_format($p->amount, 0, ',', '.') }}</td>
                        <td><span class="badge text-bg-{{ $p->status === 'paid' ? 'success' : ($p->isCanceled() ? 'secondary' : 'warning') }}">{{ $p->isCanceled() ? __('messages.canceled') : $p->status }}</span></td>
                        <td>{{ $p->created_at->format('Y-m-d') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted text-center">{{ __('messages.no_payments') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $payments->links() }}</div>
</div>

<h5 class="mt-4">{{ __('messages.active_licenses') }}</h5>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>{{ __('messages.license') }}</th><th>{{ ui('user') }}</th><th>{{ ui('plan') }}</th><th>{{ __('messages.expires') }}</th></tr></thead>
            <tbody>
                @forelse($licenses as $l)
                    <tr>
                        <td><code>{{ $l->license_key }}</code></td>
                        <td>{{ $l->user?->name ?? '-' }}</td>
                        <td>{{ $l->plan_slug }}</td>
                        <td>{{ $l->expires_at ? $l->expires_at->format('Y-m-d') : __('messages.lifetime') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted text-center">{{ __('messages.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $licenses->links() }}</div>
</div>
@endsection
