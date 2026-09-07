@extends('layouts.app')
@section('content')
<h3>{{ isset($user) ? ui('edit') . ' ' . ui('user') : ui('new_user') }}</h3>

{{-- Account status / admin actions: placed OUTSIDE the main update form to avoid nested-form submit --}}
@if (isset($user) && !$user->trashed() && $user->id !== auth()->id() && (auth()->user()->can('user.lock') || auth()->user()->can('user.edit')))
    <div class="alert alert-light d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span>Status:
                @if ($user->isPermanentlyLocked())
                    <span class="badge text-bg-danger">{{ ui('perm_locked') }}</span>
                @elseif ($user->isLocked())
                    <span class="badge text-bg-warning">{{ ui('locked') }}</span>
                @else
                    <span class="badge text-bg-success">{{ ui('active') }}</span>
                @endif
            </span>
            @if ($user->last_login_at)
                <span class="text-muted small">{{ ui('last_login', ['time' => $user->last_login_at->format('Y-m-d H:i'), 'ip' => $user->last_login_ip]) }}</span>
            @endif
            @if (auth()->user()->can('user.lock'))
                @if ($user->isLocked())
                    <button type="button" class="btn btn-sm btn-light border rounded-2 text-warning"
                        data-bs-toggle="modal" data-bs-target="#lockUserModal"
                        data-action="{{ route('users.unlock', $user) }}"
                        data-label="{{ ui('confirm_unlock') }}"
                        data-bs-title="{{ ui('unlock') }}" aria-label="{{ ui('unlock') }}" style="min-width:38px">
                        <i class="bi bi-unlock-fill"></i>
                    </button>
                @else
                    <button type="button" class="btn btn-sm btn-light border rounded-2 text-danger"
                        data-bs-toggle="modal" data-bs-target="#lockUserModal"
                        data-action="{{ route('users.lock', $user) }}"
                        data-label="{{ ui('confirm_lock') }}"
                        data-bs-title="{{ ui('lock') }}" aria-label="{{ ui('lock') }}" style="min-width:38px">
                        <i class="bi bi-lock-fill"></i>
                    </button>
                @endif
            @endif
        </div>
        @if (auth()->user()->can('user.edit'))
        <span>{{ ui('send_reset_email_label') }}
            <form method="POST" action="{{ route('users.reset-password', $user) }}" class="d-inline">@csrf
                <button type="submit" class="btn btn-sm btn-light border rounded-2" data-bs-toggle="tooltip" data-bs-title="{{ ui('send_reset_email') }}" aria-label="{{ ui('send_reset_email') }}" style="min-width:38px">
                    <i class="bi bi-envelope"></i>
                </button>
            </form>
        </span>
        @endif
    </div>
@endif

<form method="POST" action="{{ isset($user) ? route('users.update', $user) : route('users.store') }}">
    @csrf
    @if (isset($user)) @method('PUT') @endif
    <div class="card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">{{ ui('name') }}</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name ?? '') }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ ui('username') }}</label>
                    <input type="text" name="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username', $user->username ?? '') }}" required>
                    @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ ui('email') }}</label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email ?? '') }}" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ ui('phone') }}</label>
                    <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $user->phone ?? '') }}">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                {{-- password — matches profile/show + auth/register pattern (input-group + eye toggle) --}}
                <div class="col-md-6">
                    <label class="form-label">{{ ui('password') }}{{ isset($user) ? ' ' . ui('leave_blank_to_keep') : '' }}</label>
                    <div class="input-group">
                        <input type="text" name="password" id="password" class="form-control @error('password') is-invalid @enderror" {{ isset($user) ? '' : 'required' }} aria-describedby="password-error" @error('password') aria-invalid="true" @enderror value="{{ isset($user) ? old('password', '') : '' }}">
                        <button type="button" class="btn btn-outline-secondary" id="toggle-password" aria-label="{{ ui('show_password') }}">
                            <i class="bi bi-eye" id="password-icon"></i>
                        </button>
                        @unless(isset($user))
                        <button type="button" class="btn btn-outline-secondary" id="generate-password" aria-label="{{ ui('generate_password') ?? 'Generate password' }}">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                        @endunless
                    </div>
                    @error('password')<div id="password-error" class="invalid-feedback d-block w-100 mt-1" role="alert" aria-live="polite">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ ui('confirm_password') }}</label>
                    <div class="input-group">
                        <input type="text" name="password_confirmation" id="password_confirmation" class="form-control @error('password_confirmation') is-invalid @enderror" aria-describedby="password_confirmation-error" @error('password_confirmation') aria-invalid="true" @enderror value="{{ isset($user) ? old('password_confirmation', '') : '' }}">
                        <button type="button" class="btn btn-outline-secondary" id="toggle-password-confirm" aria-label="{{ ui('show_password') }}">
                            <i class="bi bi-eye" id="password-confirm-icon"></i>
                        </button>
                        @unless(isset($user))
                        <button type="button" class="btn btn-outline-secondary" id="copy-password" aria-label="Copy password">
                            <i class="bi bi-clipboard"></i>
                        </button>
                        @endunless
                    </div>
                    @error('password_confirmation')<div id="password_confirmation-error" class="invalid-feedback d-block w-100 mt-1" role="alert" aria-live="polite">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">{{ ui('roles') }}</label>
                    <div class="row">
                        @foreach ($roles as $role)
                        <div class="col-6 col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->id }}" id="role_{{ $role->id }}"
                                    {{ (isset($user) && $user->roles->contains($role->id)) ? 'checked' : '' }}>
                                <label class="form-check-label" for="role_{{ $role->id }}">{{ $role->name }}</label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('users.index') }}" class="btn btn-link">{{ ui('cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ ui('save') }}</button>
        </div>
    </div>
</form>
@include('partials.modals.lock-user-modal')
@push('scripts')
<script>
    // password visibility toggle — mirrors auth/register.blade.php + profile/show
    (function () {
        for (const [field, btn, icon] of [
            ['password', 'toggle-password', 'password-icon'],
            ['password_confirmation', 'toggle-password-confirm', 'password-confirm-icon']
        ]) {
            const pwd = document.getElementById(field);
            const b = document.getElementById(btn);
            const i = document.getElementById(icon);
            if (pwd && b && i) {
                b.addEventListener('click', function () {
                    const show = pwd.type === 'password';
                    pwd.type = show ? 'text' : 'password';
                    i.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
                });
            }
        }

        const generateBtn = document.getElementById('generate-password');
        if (generateBtn) {
            generateBtn.addEventListener('click', function () {
                const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+';
                let out = '';
                const values = crypto.getRandomValues(new Uint8Array(24));
                for (const v of values) out += charset[v % charset.length];
                document.getElementById('password').value = out;
                document.getElementById('password_confirmation').value = out;
            });
        }

        const copyBtn = document.getElementById('copy-password');
        if (copyBtn) {
            copyBtn.addEventListener('click', async function () {
                const val = document.getElementById('password').value;
                if (!val) return;
                await navigator.clipboard.writeText(val);
                const original = copyBtn.innerHTML;
                copyBtn.innerHTML = '<i class="bi bi-check"></i>';
                setTimeout(() => copyBtn.innerHTML = original, 1200);
            });
        }
    })();
</script>
@endpush
@endsection
