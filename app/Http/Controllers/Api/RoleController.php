<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Support\CatalogCache;
use App\Support\RoleHierarchyHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $roles = RoleHierarchyHelper::visibleRolesQuery($request->user())
            ->with('permissions')
            ->withCount('users')
            ->orderByDesc('level')
            ->get();

        return $this->success(RoleResource::collection($roles));
    }

    public function show(Request $request, Role $role): JsonResponse
    {
        if (!RoleHierarchyHelper::canManageRole($request->user(), $role)) {
            return $this->error('Không có quyền xem vai trò này.', 403);
        }

        $role->load('permissions');

        return $this->success(new RoleResource($role));
    }

    public function updatePermissions(Request $request, Role $role): JsonResponse
    {
        $actor = $request->user();

        if (!RoleHierarchyHelper::canManageRole($actor, $role)) {
            return $this->error('Không có quyền cập nhật vai trò này.', 403);
        }

        $validated = $request->validate([
            'permissionKeys' => ['required', 'array'],
            'permissionKeys.*' => ['string', 'exists:permissions,key'],
        ]);

        $permissionKeys = RoleHierarchyHelper::filterGrantablePermissionKeys(
            $actor,
            $validated['permissionKeys'],
        );

        if ($message = RoleHierarchyHelper::assertCanGrantPermissions($actor, $permissionKeys)) {
            return $this->error($message, 403);
        }

        $permissionIds = Permission::whereIn('key', $permissionKeys)->pluck('id');
        $role->permissions()->sync($permissionIds);
        $role->load('permissions');

        CatalogCache::bump(CatalogCache::BUCKET_NAV_MENU);

        return $this->success(
            new RoleResource($role),
            'Cập nhật phân quyền thành công'
        );
    }
}
