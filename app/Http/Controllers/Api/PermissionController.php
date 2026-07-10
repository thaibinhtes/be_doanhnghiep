<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use App\Support\RoleHierarchyHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Permission::query()->orderBy('sort_order');

        if (!RoleHierarchyHelper::isRootUser($request->user())) {
            $allowed = $request->user()?->permissionKeys() ?? [];
            $query->whereIn('key', $allowed);
        }

        $permissions = $query->get();

        $grouped = $permissions
            ->groupBy('group_name')
            ->map(fn ($items, $group) => [
                'group' => $group,
                'permissions' => PermissionResource::collection($items),
            ])
            ->values();

        return $this->success([
            'all' => PermissionResource::collection($permissions),
            'grouped' => $grouped,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!RoleHierarchyHelper::isRootUser($request->user())) {
            return $this->error('Chỉ tài khoản ROOT mới được tạo quyền mới.', 403);
        }

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:120', 'regex:/^(menu|feature)\.[a-z0-9._-]+$/', 'unique:permissions,key'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:menu,feature'],
            'groupName' => ['required', 'string', 'max:120'],
            'path' => ['nullable', 'string', 'max:255'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $permission = Permission::create([
            'key' => $validated['key'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'group_name' => $validated['groupName'],
            'path' => $validated['path'] ?? null,
            'sort_order' => $validated['sortOrder'] ?? 900,
        ]);

        return $this->success(
            new PermissionResource($permission),
            'Tạo quyền thành công',
            201,
        );
    }
}
