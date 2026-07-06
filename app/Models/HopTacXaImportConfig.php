<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HopTacXaImportConfig extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'start_row',
        'column_map',
        'value_extensions',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'column_map' => 'array',
            'value_extensions' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
