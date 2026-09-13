@extends('layouts.auth')

@section('content')
<div class="login-box">
    <div class="card card-outline card-primary">
        <div class="card-header text-center"><span class="h1">{{ config('app.name', 'Laravel') }}</span></div>
        <div class="card-body">
            <p class="login-box-msg">{{ ui('sign_up_to_account') }}</p>

            @if ($errors->any())
                <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('register.store') }}">
                @csrf
                <div class="input-group mb-3">
                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" placeholder="{{ ui('full_name') }}" value="{{ old('name') }}" required autofocus aria-describedby="name-error" @error('name') aria-invalid="true" @enderror>
                    <label for="name" class="sr-only">{{ ui('full_name') }}</label>
                    <div class="input-group-text"><i class="bi bi-person"></i></div>
                    @error('name')<div id="name-error" class="invalid-feedback d-block w-100" role="alert" aria-live="polite">{{ $message }}</div>@enderror
                </div>

                <div class="input-group mb-3">
                    <input type="text" name="username" id="username" class="form-control @error('username') is-invalid @enderror" placeholder="{{ ui('username') }}" value="{{ old('username') }}" required aria-describedby="username-error" @error('username') aria-invalid="true" @enderror>
                    <label for="username" class="sr-only">{{ ui('username') }}</label>
                    <div class="input-group-text"><i class="bi bi-person-badge"></i></div>
                    @error('username')<div id="username-error" class="invalid-feedback d-block w-100" role="alert" aria-live="polite">{{ $message }}</div>@enderror
                </div>

                <div class="input-group mb-3">
                    <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" placeholder="{{ ui('email') }}" value="{{ old('email') }}" required aria-describedby="email-error" @error('email') aria-invalid="true" @enderror>
                    <label for="email" class="sr-only">{{ ui('email') }}</label>
                    <div class="input-group-text"><i class="bi bi-envelope"></i></div>
                    @error('email')<div id="email-error" class="invalid-feedback d-block w-100" role="alert" aria-live="polite">{{ $message }}</div>@enderror
                </div>

                <div class="input-group mb-3">
                    <input type="tel" name="phone" id="phone" inputmode="numeric" class="form-control @error('phone') is-invalid @enderror" placeholder="{{ ui('phone_optional') }}" value="{{ old('phone') }}" aria-describedby="phone-error" @error('phone') aria-invalid="true" @enderror>
                    <label for="phone" class="sr-only">{{ ui('phone_optional') }}</label>
                    <div class="input-group-text"><i class="bi bi-telephone"></i></div>
                    @error('phone')<div id="phone-error" class="invalid-feedback d-block w-100" role="alert" aria-live="polite">{{ $message }}</div>@enderror
                </div>

                <div class="input-group mb-3">
                    <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" placeholder="{{ ui('password') }}" required aria-describedby="password-error" @error('password') aria-invalid="true" @enderror>
                    <label for="password" class="sr-only">{{ ui('password') }}</label>
                    <button type="button" class="input-group-text" id="toggle-password" aria-label="{{ ui('show_password') }}" style="cursor:pointer">
                        <i class="bi bi-eye" id="password-icon"></i>
                    </button>
                </div>
                @error('password')<div id="password-error" class="invalid-feedback d-block w-100 mt-1" role="alert" aria-live="polite">{{ $message }}</div>@enderror

                <div class="input-group mb-3">
                    <input type="password" name="password_confirmation" id="password_confirmation" class="form-control @error('password_confirmation') is-invalid @enderror" placeholder="{{ ui('confirm_password') }}" required aria-describedby="password_confirmation-error" @error('password_confirmation') aria-invalid="true" @enderror>
                    <label for="password_confirmation" class="sr-only">{{ ui('confirm_password') }}</label>
                    <button type="button" class="input-group-text" id="toggle-password-confirm" aria-label="{{ ui('show_password') }}" style="cursor:pointer">
                        <i class="bi bi-eye" id="password-confirm-icon"></i>
                    </button>
                </div>
                @error('password_confirmation')<div id="password_confirmation-error" class="invalid-feedback d-block w-100 mt-1" role="alert" aria-live="polite">{{ $message }}</div>@enderror

                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary w-100">{{ ui('sign_up') }}</button>
                    </div>
                </div>
            </form>

            <p class="mb-0 mt-2">
                <a href="{{ route('login') }}">{{ ui('already_have_account') }}</a>
            </p>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Toggle password visibility (shared logic — same as login page)
    (function () {
        for (const [field, btn, icon] of [
            ['password', 'toggle-password', 'password-icon'],
            ['password_confirmation', 'toggle-password-confirm', 'password-confirm-icon']
        ]) {
            const pwd = document.getElementById(field);
            const b = document.getElementById(btn);
            const i = document.getElementById(icon);
            b.addEventListener('click', function () {
                const show = pwd.type === 'password';
                pwd.type = show ? 'text' : 'password';
                i.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
            });
        }
    })();
</script>
@endpush
@endsection
