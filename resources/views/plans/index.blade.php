@extends('layouts.app')
@section('content')
@include('partials.flash-message')
<h3>{{ ui('plans') }}</h3>
<p class="text-muted">{{ ui('plans_intro') }}</p>

<div class="d-flex justify-content-end mb-2">
    <a href="{{ route('plans.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> {{ ui('new_plan') }}</a>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>{{ ui('name') }}</th><th>{{ ui('slug') }}</th><th>{{ ui('price_monthly') }}</th>
                    <th>{{ ui('limits') }}</th><th>{{ ui('features') }}</th><th>{{ ui('status') }}</th>
                    <th class="text-end">{{ ui('action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($plans as $plan)
                <tr>
                    <td>{{ $plan->name }}</td>
                    <td><span class="text-muted small">{{ $plan->slug }}</span></td>
                    <td>Rp {{ number_format($plan->price_monthly, 0, ',', '.') }}</td>
                    <td><span class="small text-muted">{{ collect(App\Models\Plan::LIMIT_KEYS)->map(fn($label,$k)=>ui($label).': '.($plan->limit($k) ?? 0))->join(', ') ?: '-' }}</span></td>
                    <td><span class="small text-muted">{{ collect($plan->features ?? [])->map(fn($f)=>config("pennant.features.$f.label", $f))->join(', ') ?: '-' }}</span></td>
                    <td>
                        @if($plan->is_active)
                            <span class="badge text-bg-success">{{ ui('active') }}</span>
                        @else
                            <span class="badge text-bg-secondary">{{ ui('inactive') }}</span>
                        @endif
                        <span class="badge text-bg-light">{{ ui('period_'.$plan->billing_period) ?: ucfirst($plan->billing_period) }}</span>
                    </td>
                    <td class="text-end">
                        <x-action-buttons :edit="route('plans.edit', $plan)" :delete="route('plans.destroy', $plan)" />
                        @if($plan->price_monthly > 0)
                        <form method="POST" action="{{ route('billing.checkout') }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="plan_slug" value="{{ $plan->slug }}">
                            <button class="btn btn-primary"><i class="bi bi-bell"></i> {{ ui('subscribe') }}</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">{{ ui('no_plans') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
