<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'isActive' => (bool) $this->is_active,
            'roleId' => $this->role_id,
            'donViId' => $this->don_vi_id,
            'phongBanId' => $this->phong_ban_id,
            'chucDanh' => $this->chuc_danh,
            'role' => new RoleResource($this->whenLoaded('role')),
            'donVi' => new DonViResource($this->whenLoaded('donVi')),
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
            'permissions' => $this->when(
                $this->relationLoaded('role') && $this->role?->relationLoaded('permissions'),
                fn () => $this->permissionKeys()
            ),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
