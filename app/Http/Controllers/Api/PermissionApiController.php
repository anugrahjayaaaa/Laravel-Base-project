<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\Auditable;
use App\Http\Requests\Rbac\PermissionStoreRequest;
use App\Http\Requests\Rbac\PermissionUpdateRequest;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @group Permissions
 *
 * Permission management (admin).
 */
class PermissionApiController extends Controller
{
    use Auditable;

    public function index(Request $request): JsonResponse
    {
        $permissions = Permission::withTrashed()
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', "%{$request->q}%"))
            ->orderBy('name')->paginate(10);

        return response()->json(PermissionResource::collection($permissions)->response()->getData(true));
    }

    public function show(Permission $permission): JsonResponse
    {
        return response()->json(new PermissionResource($permission));
    }

    public function store(PermissionStoreRequest $request): JsonResponse
    {
        $permission = Permission::create(['name' => $request->validated()['name'], 'guard_name' => 'web']);

        $this->audit($permission, 'permission_created', Auth::user());

        return response()->json(new PermissionResource($permission), 201);
    }

    /**
     * Update a permission.
     *
     * @authenticated
     */
    public function update(PermissionUpdateRequest $request, Permission $permission): JsonResponse
    {
        $permission->update(['name' => $request->validated()['name']]);

        $this->audit($permission, 'permission_updated', Auth::user());

        return response()->json(new PermissionResource($permission));
    }

    public function destroy(Permission $permission): JsonResponse
    {
        $this->audit($permission, 'permission_deleted', Auth::user());

        $permission->delete();

        return response()->json(['message' => __('messages.permission_deleted')]);
    }

    public function restore(int $id): JsonResponse
    {
        $permission = Permission::withTrashed()->findOrFail($id);

        $this->audit($permission, 'permission_restored', Auth::user());

        $permission->restore();

        return response()->json(['message' => __('messages.permission_restored')]);
    }

    public function forceDelete(int $id): JsonResponse
    {
        $permission = Permission::withTrashed()->findOrFail($id);

        $this->audit($permission, 'permission_permanently_deleted', Auth::user());

        $permission->forceDelete();

        return response()->json(['message' => __('messages.permission_permanently_deleted')]);
    }
}
