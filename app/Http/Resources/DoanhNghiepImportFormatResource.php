<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DoanhNghiepImportFormat */
class DoanhNghiepImportFormatResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'startRow' => $this->start_row,
            'columnMap' => $this->column_map ?? [],
            'valueExtensions' => $this->value_extensions ?? [],
            'donViId' => $this->don_vi_id,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
