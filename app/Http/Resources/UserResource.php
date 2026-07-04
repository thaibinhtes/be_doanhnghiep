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
            'role' => new RoleResource($this->whenLoaded('role')),
            'donVi' => new DonViResource($this->whenLoaded('donVi')),
            'permissions' => $this->when(
                $this->relationLoaded('role') && $this->role?->relationLoaded('permissions'),
                fn () => $this->permissionKeys()
            ),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
