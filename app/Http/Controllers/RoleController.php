<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Auditable;
use App\Http\Controllers\Concerns\Sortable;
use App\Http\Requests\BulkActionRequest;
use App\Http\Requests\Rbac\RoleStoreRequest;
use App\Http\Requests\Rbac\RoleUpdateRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\BulkDeleteService;
use App\Services\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    use Sortable, Auditable;

    public function __construct(private BulkDeleteService $bulk) {}

    public function index(Request $request): View
    {
        $roles = Role::withTrashed()->with('permissions')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->q.'%'))
            ->when(true, fn ($q) => $this->sortIndex($q, $request, 'name', ['name']))
            ->paginate(10)->withQueryString();

        return view('access.roles.index', compact('roles'));
    }

    public function create(): View
    {
        $permissions = Permission::orderBy('name')->get();

        return view('access.roles.create', compact('permissions'));
    }

    public function store(RoleStoreRequest $request): RedirectResponse
    {
        $plan = $request->user()->can('feature.manage') ? null : PlanService::for();
        $data = $request->validated();

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $perms = $this->filterPermissions($data['permissions'] ?? [], $plan);
        $role->syncPermissions($perms);

        $this->audit($role, 'role_created', $request->user());

        return redirect()->route('roles.index')->with('success', __('messages.role_created'));
    }

    public function edit(Role $role): View
    {
        $permissions = Permission::orderBy('name')->get();
        $role->load('permissions');

        return view('access.roles.edit', compact('role', 'permissions'));
    }

    public function update(RoleUpdateRequest $request, Role $role): RedirectResponse
    {
        $plan = $request->user()->can('feature.manage') ? null : PlanService::for();
        $data = $request->validated();

        $role->update(['name' => $data['name']]);
        $perms = $this->filterPermissions($data['permissions'] ?? [], $plan);
        $role->syncPermissions($perms);

        $this->audit($role, 'role_updated', $request->user());

        return redirect()->route('roles.index')->with('success', __('messages.role_updated'));
    }

    /**
     * Filter permission IDs through the plan's allowed_permissions snapshot.
     * When $plan is null (super-admin bypass), all permission IDs pass through.
     * When allowed_permissions is empty, all are denied (deny by default).
     */
    private function filterPermissions(array $permIds, ?PlanService $plan): array
    {
        if (! $plan) {
            return array_map('intval', $permIds);
        }
        $allowedNames = $plan->allowedPermissions();
        if ($allowedNames === []) {
            return [];
        }
        $allowedIds = Permission::whereIn('name', $allowedNames)->pluck('id')->all();

        return array_intersect($permIds, $allowedIds);
    }

    public function bulk(BulkActionRequest $request): RedirectResponse
    {
        $force = $request->input('action') === 'force';
        $done = $this->bulk->run(
            Role::class,
            $request->input('ids'),
            $force,
            'role',
            fn (Role $role) => $role->name === 'super-admin', // protect super-admin
        );

        $key = $force ? 'roles_permanently_deleted_count' : 'roles_deleted_count';

        return redirect()->route('roles.index')->with('success', __('messages.'.$key, ['count' => $done]));
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === 'super-admin') {
            return redirect()->route('roles.index')->with('error', __('messages.cannot_delete_super_admin'));
        }

        $this->audit($role, 'role_deleted', request()->user());

        $role->delete();

        return redirect()->route('roles.index')->with('success', __('messages.role_deleted'));
    }

    public function restore(int $id): RedirectResponse
    {
        $role = Role::withTrashed()->findOrFail($id);

        $this->audit($role, 'role_restored', request()->user());

        $role->restore();

        return redirect()->route('roles.index')->with('success', __('messages.role_restored'));
    }

    public function forceDelete(int $id): RedirectResponse
    {
        if (Role::withTrashed()->findOrFail($id)->name === 'super-admin') {
            return redirect()->route('roles.index')->with('error', __('messages.cannot_permanently_delete_super_admin'));
        }

        $role = Role::withTrashed()->findOrFail($id);

        $this->audit($role, 'role_permanently_deleted', request()->user());

        $role->forceDelete();

        return redirect()->route('roles.index')->with('success', __('messages.role_permanently_deleted'));
    }
}
