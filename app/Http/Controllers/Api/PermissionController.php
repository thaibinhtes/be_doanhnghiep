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
}
