<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'permissionKeys' => $this->when(
                $this->relationLoaded('permissions'),
                fn () => $this->permissions->pluck('key')->values()
            ),
            'usersCount' => $this->when(isset($this->users_count), $this->users_count),
        ];
    }
}
