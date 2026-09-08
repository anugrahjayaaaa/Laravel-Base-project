<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Auditable;
use App\Http\Controllers\Concerns\Sortable;
use App\Http\Requests\BulkActionRequest;
use App\Http\Requests\Rbac\PermissionStoreRequest;
use App\Http\Requests\Rbac\PermissionUpdateRequest;
use App\Models\Permission;
use App\Services\BulkDeleteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PermissionController extends Controller
{
    use Sortable, Auditable;

    public function __construct(private BulkDeleteService $bulk) {}

    public function index(Request $request): View
    {
        $permissions = Permission::withTrashed()
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->q.'%'))
            ->when(true, fn ($q) => $this->sortIndex($q, $request, 'name', ['name', 'guard_name']))
            ->paginate(10)->withQueryString();

        return view('access.permissions.index', compact('permissions'));
    }

    public function create(): View
    {
        return view('access.permissions.create');
    }

    public function store(PermissionStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $permission = Permission::create(['name' => $data['name'], 'guard_name' => 'web']);

        $this->audit($permission, 'permission_created', $request->user());

        return redirect()->route('permissions.index')->with('success', __('messages.permission_created'));
    }

    public function edit(Permission $permission): View
    {
        return view('access.permissions.edit', compact('permission'));
    }

    public function update(PermissionUpdateRequest $request, Permission $permission): RedirectResponse
    {
        $data = $request->validated();

        $permission->update(['name' => $data['name']]);

        $this->audit($permission, 'permission_updated', $request->user());

        return redirect()->route('permissions.index')->with('success', __('messages.permission_updated'));
    }

    public function bulk(BulkActionRequest $request): RedirectResponse
    {
        $force = $request->input('action') === 'force';
        $done = $this->bulk->run(Permission::class, $request->input('ids'), $force, 'permission');

        $key = $force ? 'permissions_permanently_deleted_count' : 'permissions_deleted_count';

        return redirect()->route('permissions.index')->with('success', __('messages.'.$key, ['count' => $done]));
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $this->audit($permission, 'permission_deleted', request()->user());

        $permission->delete();

        return redirect()->route('permissions.index')->with('success', __('messages.permission_deleted'));
    }

    public function restore(int $id): RedirectResponse
    {
        $permission = Permission::withTrashed()->findOrFail($id);

        $this->audit($permission, 'permission_restored', request()->user());

        $permission->restore();

        return redirect()->route('permissions.index')->with('success', __('messages.permission_restored'));
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $permission = Permission::withTrashed()->findOrFail($id);

        $this->audit($permission, 'permission_permanently_deleted', request()->user());

        $permission->forceDelete();

        return redirect()->route('permissions.index')->with('success', __('messages.permission_permanently_deleted'));
    }
}
