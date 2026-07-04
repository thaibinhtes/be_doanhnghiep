<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\HanhChinhMapping */
class HanhChinhMappingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'groupNo' => $this->group_no,
            'xaPhuongCuCode' => $this->xa_phuong_cu_code,
            'xaPhuongMoiCode' => $this->xa_phuong_moi_code,
            'newUnitType' => $this->new_unit_type,
            'notes' => $this->notes,
            'xaPhuongCu' => new XaPhuongCuResource($this->whenLoaded('xaPhuongCu')),
            'xaPhuongMoi' => new XaPhuongResource($this->whenLoaded('xaPhuongMoi')),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
