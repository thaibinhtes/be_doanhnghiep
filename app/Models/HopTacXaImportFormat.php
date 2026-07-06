<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HopTacXaImportFormat extends Model
{
    protected $fillable = [
        'user_id',
        'don_vi_id',
        'name',
        'start_row',
        'column_map',
        'value_extensions',
    ];

    protected function casts(): array
    {
        return [
            'column_map' => 'array',
            'value_extensions' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function donVi(): BelongsTo
    {
        return $this->belongsTo(DonVi::class, 'don_vi_id');
    }
}
