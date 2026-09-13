@extends('layouts.app')
@section('content')
@include('partials.flash-message')
<h3>{{ ui('billing') }}</h3>

<div class="row g-3">
    {{-- Current plan / license --}}
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between">
                <span>{{ __('messages.current_plan') }}</span>
                @if($license)
                    <span class="badge text-bg-success">{{ ui('active') }}</span>
                @else
                    <span class="badge text-bg-secondary">{{ ui('inactive') }}</span>
                @endif
            </div>
            <div class="card-body">
                @if($license)
                    <h5 class="card-title">{{ $plan?->name ?? $license->plan_slug }}</h5>
                    @php($periodKey = match($license->type) {
                        'recurring' => 'period_monthly',
                        'lifetime', 'manual' => 'period_lifetime',
                        default => 'period_monthly',
                    })
                    <p class="text-muted">{{ ui('billing_period') }}: {{ ui($periodKey) }}</p>
                    <p>{{ __('messages.license') }}: <code>{{ $license->license_key }}</code></p>
                    <p>{{ __('messages.expires') }}: {{ $license->expires_at ? $license->expires_at->format('Y-m-d') : __('messages.lifetime') }}</p>
                    <form method="POST" action="{{ route('billing.cancel') }}" class="mt-3">
                        @csrf
                        <button class="btn btn-outline-danger" onclick="return confirm('{{ __('messages.confirm_cancel') }}')">{{ __('messages.cancel_subscription') }}</button>
                    </form>
                @else
                    <p class="text-muted">{{ __('messages.no_active_subscription') }}</p>
                    <a href="{{ route('plans.index') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> {{ ui('subscribe') }}</a>
                @endif
            </div>
        </div>
    </div>

    {{-- Quick stats --}}
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">{{ __('messages.history') }}</div>
            <div class="card-body">
                <p>{{ __('messages.total_payments') }}: <strong>{{ $payments->total() }}</strong></p>
                <p>{{ __('messages.spent') }}: <strong>Rp {{ number_format($payments->sum('amount'), 0, ',', '.') }}</strong></p>
            </div>
        </div>
    </div>
</div>

<h5 class="mt-4">{{ ui('payment_history') }}</h5>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>{{ ui('invoice') }}</th>
                    <th>{{ ui('plan') }}</th>
                    <th>{{ ui('amount') }}</th>
                    <th>{{ ui('status') }}</th>
                    <th>{{ ui('date') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $p)
                    <tr>
                        <td><code>{{ $p->invoice_no }}</code></td>
                        <td>{{ $p->plan_slug }}</td>
                        <td>Rp {{ number_format($p->amount, 0, ',', '.') }}</td>
                        <td>
                            <span class="badge text-bg-{{ $p->status === 'paid' ? 'success' : ($p->isCanceled() ? 'secondary' : 'warning') }}">
                                {{ $p->isCanceled() ? __('messages.canceled') : $p->status }}
                            </span>
                        </td>
                        <td>{{ $p->created_at->format('Y-m-d') }}</td>
                        <td>
                            @if($p->isPaid())
                                <a href="{{ route('billing.invoice', $p) }}" class="btn btn-sm btn-outline-secondary">{{ ui('download') }}</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">{{ __('messages.no_payments') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $payments->links() }}</div>
</div>
@endsection
