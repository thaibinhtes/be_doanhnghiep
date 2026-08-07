<?php

namespace App\Http\Resources;

use App\Support\AuthProfileCache;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Slim user payload for /auth/me, login, refresh — avoids nesting full PermissionResource list.
 */
class AuthUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $permissionKeys = AuthProfileCache::permissionKeysForRole(
            $this->role_id ? (int) $this->role_id : null,
        );

        $role = $this->whenLoaded('role', function () use ($permissionKeys) {
            if ($this->role === null) {
                return null;
            }

            return [
                'id' => $this->role->id,
                'name' => $this->role->name,
                'slug' => $this->role->slug,
                'level' => (int) $this->role->level,
                'description' => $this->role->description,
                'permissionKeys' => $permissionKeys,
            ];
        });

        $donVi = $this->whenLoaded('donVi', function () {
            if ($this->donVi === null) {
                return null;
            }

            return [
                'id' => $this->donVi->id,
                'parentId' => $this->donVi->parent_id,
                'cap' => $this->donVi->cap,
                'ma' => $this->donVi->ma,
                'ten' => $this->donVi->ten,
                'isActive' => (bool) $this->donVi->is_active,
            ];
        });

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'isActive' => (bool) $this->is_active,
            'roleId' => $this->role_id,
            'donViId' => $this->don_vi_id,
            'phongBanId' => $this->phong_ban_id,
            'chucDanh' => $this->chuc_danh,
            'role' => $role,
            'donVi' => $donVi,
            'phongBan' => $this->whenLoaded('phongBan', function () {
                if ($this->phongBan === null) {
                    return null;
                }

                return [
                    'id' => $this->phongBan->id,
                    'ma' => $this->phongBan->ma,
                    'ten' => $this->phongBan->ten,
                ];
            }),
            'permissions' => $permissionKeys,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
