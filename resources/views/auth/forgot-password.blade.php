@extends('layouts.auth')

@section('content')
<div class="login-box">
    <div class="card card-outline card-primary">
        <div class="card-header text-center"><span class="h1">{{ config('app.name', 'Laravel') }}</span></div>
        <div class="card-body">
            <p class="login-box-msg">{{ ui('forgot_password_intro') }}</p>

            @if ($errors->any())
                <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
            @endif

            @if (session('status'))
                <div class="alert alert-success py-2">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="input-group mb-3">
                    <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" placeholder="{{ ui('email') }}" value="{{ old('email') }}" required autofocus aria-describedby="email-error" @error('email') aria-invalid="true" @enderror>
                    <label for="email" class="sr-only">{{ ui('email') }}</label>
                    <div class="input-group-text"><i class="bi bi-envelope"></i></div>
                    @error('email')<div id="email-error" class="invalid-feedback d-block w-100" role="alert" aria-live="polite">{{ $message }}</div>@enderror
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary w-100">{{ ui('send_reset_link') }}</button>
                    </div>
                </div>
            </form>

            <p class="mb-0 mt-2"><a href="{{ route('login') }}">{{ ui('back_to_login') }}</a></p>
        </div>
    </div>
</div>
@endsection
