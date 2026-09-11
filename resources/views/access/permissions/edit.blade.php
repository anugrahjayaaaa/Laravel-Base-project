@extends('layouts.app')
@section('content')
<h3>{{ isset($permission) ? ui('edit') . ' ' . ui('permission') : ui('new_permission') }}</h3>
<form method="POST" action="{{ isset($permission) ? route('permissions.update', $permission) : route('permissions.store') }}">
    @csrf
    @if (isset($permission)) @method('PUT') @endif
    <div class="card">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">{{ ui('name') }}</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" id="permission-name" placeholder="e.g. user.edit" value="{{ old('name', $permission->name ?? '') }}" required aria-describedby="permission-name-error" @error('name') aria-invalid="true" @enderror>
                @error('name')<div id="permission-name-error" class="invalid-feedback d-block" role="alert" aria-live="polite">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('permissions.index') }}" class="btn btn-link">{{ ui('cancel') }}</a>
            <button class="btn btn-primary">{{ ui('save') }}</button>
        </div>
    </div>
</form>
@endsection
