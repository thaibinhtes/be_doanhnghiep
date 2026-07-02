<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends ApiController
{
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return $this->success(RoleResource::collection($roles));
    }

    public function show(Role $role): JsonResponse
    {
        $role->load('permissions');

        return $this->success(new RoleResource($role));
    }

    public function updatePermissions(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permissionKeys' => ['required', 'array'],
            'permissionKeys.*' => ['string', 'exists:permissions,key'],
        ]);

        $permissionIds = Permission::whereIn('key', $validated['permissionKeys'])->pluck('id');
        $role->permissions()->sync($permissionIds);
        $role->load('permissions');

        return $this->success(
            new RoleResource($role),
            'Cập nhật phân quyền thành công'
        );
    }
}
