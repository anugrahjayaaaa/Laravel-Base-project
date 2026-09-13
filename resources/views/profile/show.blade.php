@extends('layouts.app')
@section('content')
@php($title = ui('profile'))
@include('partials.flash-message')
<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header">{{ ui('profile') }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">{{ ui('name') }}</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
                        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ ui('username') }}</label>
                        <input type="text" class="form-control" value="{{ $user->username }}" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ ui('email') }}</label>
                        <input type="text" class="form-control" value="{{ $user->email }}" disabled>
                        @if ($user->hasVerifiedEmail())
                            <div class="form-text text-success">{{ ui('email_verified') }}</div>
                        @else
                            <div class="form-text text-warning">{{ ui('email_not_verified') }}</div>
                            <form method="POST" action="{{ route('verification.resend') }}" class="mt-2">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ ui('resend_verification') }}</button>
                            </form>
                        @endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ ui('phone') }}</label>
                        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $user->phone) }}">
                        @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
            </div>
            <div class="card-footer d-flex justify-content-end gap-2">
                <button type="submit" class="btn btn-primary">{{ ui('update_profile') }}</button>
            </div>
            </form>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header">{{ ui('change_password') }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('profile.password') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">{{ ui('current_password') }}</label>
                        <input type="password" name="current_password" id="current_password" class="form-control @error('current_password') is-invalid @enderror" required>
                        @error('current_password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ ui('new_password') }}</label>
                        <div class="input-group">
                            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" required aria-describedby="password-error" @error('password') aria-invalid="true" @enderror>
                            <button type="button" class="btn btn-outline-secondary" id="toggle-password" aria-label="{{ ui('show_password') }}">
                                <i class="bi bi-eye" id="password-icon"></i>
                            </button>
                        </div>
                        @error('password')<div id="password-error" class="invalid-feedback d-block w-100 mt-1" role="alert" aria-live="polite">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ ui('confirm_password') }}</label>
                        <div class="input-group">
                            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control @error('password_confirmation') is-invalid @enderror" required aria-describedby="password_confirmation-error" @error('password_confirmation') aria-invalid="true" @enderror>
                            <button type="button" class="btn btn-outline-secondary" id="toggle-password-confirm" aria-label="{{ ui('show_password') }}">
                                <i class="bi bi-eye" id="password-confirm-icon"></i>
                            </button>
                        </div>
                        @error('password_confirmation')<div id="password_confirmation-error" class="invalid-feedback d-block w-100 mt-1" role="alert" aria-live="polite">{{ $message }}</div>@enderror
                    </div>
            </div>
            <div class="card-footer d-flex justify-content-end gap-2">
                <button type="submit" class="btn btn-warning">{{ ui('change_password') }}</button>
            </div>
            </form>
        </div>
    </div>
</div>
@push('scripts')
<script>
    // password visibility toggle — mirrors auth/register.blade.php pattern
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
    })();
</script>
@endpush
@endsection
