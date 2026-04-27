<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberCompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'memberId' => $this->member_id,
            'doanhNghiepId' => $this->doanh_nghiep_id,
            'dateJoin' => $this->date_join,
            'position' => $this->position,
            'investmentAmount' => $this->investment_amount,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'member' => $this->whenLoaded('member', fn () => new MemberResource($this->member)),
            'doanhNghiep' => $this->whenLoaded('doanhNghiep', fn () => new DoanhNghiepResource($this->doanhNghiep)),
        ];
    }
}
