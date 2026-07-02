<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DnTrangThaiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ma' => $this->ma,
            'ten' => $this->ten,
            'loai' => $this->loai,
            'yeuCauLyDo' => (bool) $this->yeu_cau_ly_do,
            'hienThiBaoCao' => (bool) $this->hien_thi_bao_cao,
            'thuTuBaoCao' => $this->thu_tu_bao_cao,
            'macDinh' => (bool) $this->mac_dinh,
            'isActive' => (bool) $this->is_active,
            'moTa' => $this->mo_ta,
            'companiesCount' => $this->whenCounted('doanhNghieps'),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
