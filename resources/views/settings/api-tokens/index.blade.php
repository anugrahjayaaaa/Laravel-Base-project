@extends('layouts.app')
@section('content')
@include('partials.flash-message')
<h3>{{ ui('api_tokens') }}</h3>

@if($newToken)
<div class="alert alert-success d-flex align-items-start justify-content-between gap-2" id="newTokenAlert">
    <div>
        <strong>{{ ui('new_token_copy_now') }}</strong>
        <code class="d-block mt-1" id="newTokenValue">{{ $newToken }}</code>
    </div>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="copyTokenBtn" data-clipboard-target="#newTokenValue">
        <i class="bi bi-clipboard"></i> {{ ui('copy') }}
    </button>
</div>
@endif

<form method="POST" action="{{ route('api-tokens.store') }}" class="row g-2 mb-3">
    @csrf
    <div class="col-md-4">
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="{{ ui('token_name') }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-auto">
        <button class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> {{ ui('create') }}</button>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead><tr><th>{{ ui('name') }}</th><th>{{ ui('abilities') }}</th><th>{{ ui('created') }}</th><th>{{ ui('last_used') }}</th><th></th></tr></thead>
            <tbody>
            @forelse($tokens as $token)
                <tr>
                    <td>{{ $token->name }}</td>
                    <td>{{ implode(', ', $token->abilities) }}</td>
                    <td>{{ $token->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>{{ $token->last_used_at?->diffForHumans() ?? 'never' }}</td>
                    <td>
                        <form method="POST" action="{{ route('api-tokens.destroy', $token) }}" onsubmit="return confirm('{{ ui('revoke_this_token') }}')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted">{{ ui('no_tokens_yet') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('copyTokenBtn')?.addEventListener('click', function () {
    const text = document.getElementById('newTokenValue').textContent.trim();
    const btn = this;
    const done = () => {
        const icon = btn.querySelector('i');
        icon.className = 'bi bi-check2';
        btn.textContent = ' {{ ui('copied') }}';
        setTimeout(() => { icon.className = 'bi bi-clipboard'; btn.textContent = ' {{ ui('copy') }}'; }, 1500);
    };
    if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(() => {});
    } else {
        const ta = document.createElement('textarea'); // ponytail: fallback for non-secure context
        ta.value = text; document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
        ta.remove();
    }
});
</script>
@endpush
