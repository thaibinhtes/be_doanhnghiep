<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\XaPhuongCu */
class XaPhuongCuResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'fullName' => $this->full_name,
            'unitType' => $this->unit_type,
            'quanHuyenCuCode' => $this->quan_huyen_cu_code,
            'quanHuyenCu' => new QuanHuyenCuResource($this->whenLoaded('quanHuyen')),
            'mappings' => HanhChinhMappingResource::collection($this->whenLoaded('mappings')),
            'mapping' => $this->when(
                $this->relationLoaded('mappings') && $this->mappings->isNotEmpty(),
                fn () => new HanhChinhMappingResource($this->mappings->first()),
            ),
        ];
    }
}
