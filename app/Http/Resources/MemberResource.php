<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
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
            'cccd' => $this->cccd,
            'fullName' => $this->full_name,
            'birthday' => $this->birthday,
            'gender' => $this->gender,
            'dateJoin' => $this->date_join,
            'status' => $this->status,
            'position' => $this->position,
            'investmentAmount' => $this->investment_amount,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'doanhNghieps' => $this->whenLoaded('doanhNghieps', fn () => DoanhNghiepResource::collection($this->doanhNghieps)),
        ];
    }
}
