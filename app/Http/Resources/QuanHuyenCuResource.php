<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\QuanHuyenCu */
class QuanHuyenCuResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'fullName' => $this->full_name,
            'tinhThanhCuCode' => $this->tinh_thanh_cu_code,
            'tinhThanhCu' => new TinhThanhCuResource($this->whenLoaded('tinhThanh')),
        ];
    }
}
