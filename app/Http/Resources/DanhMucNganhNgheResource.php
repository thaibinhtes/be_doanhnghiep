<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DanhMucNganhNgheResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parentId' => $this->parent_id,
            'cap' => $this->cap,
            'ma' => $this->ma,
            'ten' => $this->ten,
            'thuTu' => $this->thu_tu,
            'isActive' => (bool) $this->is_active,
            'childrenCount' => $this->whenCounted('children'),
            'parent' => new self($this->whenLoaded('parent')),
            'children' => self::collection($this->whenLoaded('children')),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
