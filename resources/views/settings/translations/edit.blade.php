@extends('layouts.app')
@section('content')
@include('partials.flash-message')

<h3>{{ ui('edit_translation') }}: {{ $line->group }}.{{ $line->key }}</h3>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('translations.update', $line) }}">
            @csrf
            @method('PUT')
            @foreach($locales as $locale)
                @php($errorId = $locale.'-error')
                <div class="mb-3">
                    <label class="form-label text-uppercase">{{ $locale }}</label>
                    <input type="text" name="{{ $locale }}" id="{{ $locale }}" class="form-control @error($locale) is-invalid @enderror"
                           value="{{ old($locale, $line->text[$locale] ?? '') }}" required aria-describedby="{{ $errorId }}" @error($locale) aria-invalid="true" @enderror>
                    @error($locale)<div id="{{ $errorId }}" class="invalid-feedback d-block w-100" role="alert" aria-live="polite">{{ $message }}</div>@enderror
                </div>
            @endforeach
            <div class="card-footer d-flex justify-content-end gap-2">
                <a href="{{ route('translations.index') }}" class="btn btn-light border">{{ ui('cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ ui('save') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
