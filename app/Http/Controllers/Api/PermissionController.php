<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;

class PermissionController extends ApiController
{
    public function index(): JsonResponse
    {
        $permissions = Permission::orderBy('sort_order')->get();

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
