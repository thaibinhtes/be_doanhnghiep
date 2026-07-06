<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\HopTacXaImportConfig */
class HopTacXaImportConfigResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'startRow' => $this->start_row,
            'columnMap' => $this->column_map ?? [],
            'valueExtensions' => $this->value_extensions ?? [],
            'sortOrder' => $this->sort_order,
        ];
    }
}
