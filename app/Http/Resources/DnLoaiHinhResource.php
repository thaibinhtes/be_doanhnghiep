<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DnLoaiHinhResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ma' => $this->ma,
            'ten' => $this->ten,
            'thuTu' => $this->thu_tu,
            'macDinh' => (bool) $this->mac_dinh,
            'isActive' => (bool) $this->is_active,
            'moTa' => $this->mo_ta,
            'companiesCount' => $this->whenCounted('doanhNghieps'),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
