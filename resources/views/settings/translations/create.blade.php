@extends('layouts.app')
@section('title', ui('add_translation'))
@section('content')
@php($title = ui('add_translation'))
@include('partials.flash-message')

<h3>{{ ui('add_translation') }}</h3>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('translations.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label text-uppercase">{{ ui('group') }}</label>
                <input type="text" name="group" id="group" class="form-control @error('group') is-invalid @enderror"
                       value="{{ old('group') }}" required aria-describedby="group-error" @error('group') aria-invalid="true" @enderror>
                @error('group')<div id="group-error" class="invalid-feedback d-block w-100" role="alert" aria-live="polite">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label text-uppercase">{{ ui('key') }}</label>
                <input type="text" name="key" id="key" class="form-control @error('key') is-invalid @enderror"
                       value="{{ old('key') }}" required aria-describedby="key-error" @error('key') aria-invalid="true" @enderror>
                @error('key')<div id="key-error" class="invalid-feedback d-block w-100" role="alert" aria-live="polite">{{ $message }}</div>@enderror
            </div>
            @foreach($locales as $locale)
                @php($errorId = $locale.'-error')
                <div class="mb-3">
                    <label class="form-label text-uppercase">{{ $locale }}</label>
                    <input type="text" name="{{ $locale }}" id="{{ $locale }}" class="form-control @error($locale) is-invalid @enderror"
                           value="{{ old($locale) }}" required aria-describedby="{{ $errorId }}" @error($locale) aria-invalid="true" @enderror>
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
