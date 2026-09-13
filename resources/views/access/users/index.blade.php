@extends('layouts.app')
@section('content')
@include('partials.flash-message')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>{{ ui('users') }}</h3>
    @can('user.create')
    <a href="{{ route('users.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> {{ ui('new_user') }}</a>
    @endcan
</div>

<div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
    <form method="GET" class="d-flex flex-grow-1" style="max-width:420px">
        <div class="input-group input-group-sm shadow-sm w-100">
            <span class="input-group-text bg-body border-0"><i class="bi bi-search"></i></span>
            <input type="text" name="q" class="form-control bg-body border-0" placeholder="{{ ui('search_name_username_email') }}" value="{{ request('q') }}">
            <button class="btn btn-primary px-3" type="submit">{{ ui('search') }}</button>
        </div>
    </form>
    @include('partials.bulk-actions', ['bulkRoute' => route('users.bulk'), 'canSoft' => auth()->user()->can('user.delete'), 'canForce' => auth()->user()->can('user.force-delete')])
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle m-0">
            <thead>
                <tr>
                    <th style="width:38px"><input class="form-check-input" type="checkbox" form="bulk-form" id="bulk-select-all"></th>
                    <th>#</th>
                    <x-sortable-th label="{{ ui('user') }}" column="name" :sort="request('sort')" :dir="request('dir', 'asc')" />
                    <x-sortable-th label="{{ ui('username') }}" column="username" :sort="request('sort')" :dir="request('dir', 'asc')" />
                    <x-sortable-th label="{{ ui('email') }}" column="email" :sort="request('sort')" :dir="request('dir', 'asc')" />
                    <th>{{ ui('roles') }}</th><th>{{ ui('status') }}</th><th class="text-end">{{ ui('action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                <tr class="{{ $user->trashed() ? 'row-deleted' : '' }}">
                    <td><input class="form-check-input" type="checkbox" form="bulk-form" name="ids[]" value="{{ $user->id }}"></td>
                    <td class="text-muted">{{ $users->firstItem() + $loop->index }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar avatar-sm rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:32px;height:32px">{{ strtoupper(substr($user->name,0,1)) }}</span>
                            <div>
                                <span class="fw-medium">{{ $user->name }}</span>
                                @if($user->trashed())<span class="badge text-bg-danger">{{ ui('deleted') }}</span>@endif
                            </div>
                        </div>
                    </td>
                    <td>{{ $user->username }}</td>
                    <td class="text-muted">{{ $user->email }}</td>
                    <td>
                        @foreach ($user->roles as $role)
                            <span class="badge text-bg-info me-1">{{ $role->name }}</span>
                        @endforeach
                        @if ($user->roles->isEmpty())<span class="text-muted">-</span>@endif
                    </td>
                    <td>
                        @if ($user->isPermanentlyLocked())
                            <span class="badge text-bg-danger">{{ ui('perm_locked') }}</span>
                        @elseif ($user->isLocked())
                            <span class="badge text-bg-warning">{{ ui('locked') }}</span>
                        @else
                            <span class="badge text-bg-success">{{ ui('active') }}</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-1">
                            <x-action-buttons
                                :edit="!$user->trashed() ? route('users.edit', $user) : null"
                                :restore="$user->trashed() && auth()->user()->can('user.restore') ? route('users.restore', $user->id) : null"
                                :delete="!$user->trashed() && $user->id !== auth()->id() ? route('users.destroy', $user) : null"
                                :forceDelete="$user->trashed() && auth()->user()->can('user.force-delete') ? route('users.forceDelete', $user->id) : null" />
                            @if (!$user->trashed() && $user->id !== auth()->id() && auth()->user()->can('user.edit'))
                            <form method="POST" action="{{ route('users.reset-password', $user) }}" class="d-inline">@csrf
                                <button type="submit" class="btn btn-sm btn-light border rounded-2" data-bs-toggle="tooltip" data-bs-title="{{ ui('send_reset_email') }}" aria-label="{{ ui('send_reset_email') }}" style="min-width:38px">
                                    <i class="bi bi-envelope"></i>
                                </button>
                            </form>
                            @endif
                            @if (!$user->trashed() && $user->id !== auth()->id() && auth()->user()->can('user.lock'))
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
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">{{ ui('no_users_found') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@include('partials.pagination-info', ['items' => $users])
{{ $users->links() }}
@include('partials.modals.delete-modal')
@include('partials.modals.force-delete-modal')
@include('partials.modals.lock-user-modal')
@endsection
