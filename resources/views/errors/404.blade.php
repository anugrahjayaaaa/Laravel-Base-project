@extends('layouts.auth')

@section('content')
<style>.login-box { max-width: 480px; }</style>
<div class="login-box">
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-emoji-frown display-1 text-muted"></i>
            <h1 class="display-4 fw-bold my-3">404</h1>
            <h3>{{ ui('page_not_found') }}</h3>
            <p class="text-muted">{{ ui('page_not_found_body') }}</p>
            <a href="{{ url('/') }}" class="btn btn-primary">{{ ui('go_back_to_dashboard') }}</a>
        </div>
    </div>
</div>
@endsection
