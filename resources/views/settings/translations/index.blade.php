@extends('layouts.app')
@section('title', ui('translations'))
@section('content')
@php($u = auth()->user())
<div class="content-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="m-0 h3">{{ ui('translations') }}</h1>
            @can('translation.create')
            <a href="{{ route('translations.create') }}" class="btn btn-sm btn-primary">{{ ui('add') }} {{ ui('translation') }}</a>
            @endcan
        </div>
        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
            <form method="GET" class="d-flex flex-grow-1" style="max-width:420px">
                <div class="input-group input-group-sm shadow-sm w-100">
                    <span class="input-group-text bg-body border-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control bg-body border-0" placeholder="{{ ui('search_translation') }}" value="{{ request('q') }}">
                    <button class="btn btn-primary px-3" type="submit">{{ ui('search') }}</button>
                </div>
            </form>
        </div>
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle m-0">
                        <thead class="table-light">
                            <tr>
                                <x-sortable-th label="{{ ui('group') }}" column="group" :sort="request('sort')" :dir="request('dir', 'asc')" />
                                <x-sortable-th label="{{ ui('key') }}" column="key" :sort="request('sort')" :dir="request('dir', 'asc')" />
                                <th>EN</th>
                                <th>ID</th>
                                <th class="text-end">{{ ui('action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lines as $line)
                                <tr>
                                    <td><span class="badge text-bg-secondary">{{ $line->group }}</span></td>
                                    <td><code>{{ $line->key }}</code></td>
                                    <td class="text-muted">{{ $line->text['en'] ?? '' }}</td>
                                    <td class="text-muted">{{ $line->text['id'] ?? '' }}</td>
                                    <td class="text-end">
                                        <x-action-buttons :edit="route('translations.edit', $line)" />
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">{{ ui('no_translations') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($lines->hasPages())
            <div class="card-footer">
                {{ $lines->links() }}
            </div>
            @endif
        </div>
        {{-- back to content-header --}}
    </div>
</div>
@endsection
