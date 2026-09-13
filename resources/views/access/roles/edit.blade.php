@extends('layouts.app')
@section('content')
<h3>{{ isset($role) ? ui('edit') . ' ' . ui('role') : ui('new_role') }}</h3>
<form method="POST" action="{{ isset($role) ? route('roles.update', $role) : route('roles.store') }}">
    @csrf
    @if (isset($role)) @method('PUT') @endif
    <div class="card">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">{{ ui('name') }}</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" id="role-name" value="{{ old('name', $role->name ?? '') }}" required aria-describedby="role-name-error" @error('name') aria-invalid="true" @enderror>
                @error('name')<div id="role-name-error" class="invalid-feedback d-block w-100" role="alert" aria-live="polite">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">{{ ui('permissions') }}</label>
                <div class="row">
                    @foreach ($permissions as $perm)
                    <div class="col-6 col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $perm->id }}"
                                id="perm_{{ $perm->id }}" {{ (isset($role) && $role->permissions->contains($perm->id)) ? 'checked' : '' }}>
                            <label class="form-check-label" for="perm_{{ $perm->id }}">{{ $perm->name }}</label>
                        </div>
                    </div>
                    @endforeach
                </div>
                @error('permissions')<div class="invalid-feedback d-block w-100" role="alert" aria-live="polite">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('roles.index') }}" class="btn btn-link">{{ ui('cancel') }}</a>
            <button class="btn btn-primary">{{ ui('save') }}</button>
        </div>
    </div>
</form>
@endsection
