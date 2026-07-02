<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\XaPhuong */
class XaPhuongResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'fullName' => $this->full_name,
            'tinhThanhCode' => $this->tinh_thanh_code,
        ];
    }
}
