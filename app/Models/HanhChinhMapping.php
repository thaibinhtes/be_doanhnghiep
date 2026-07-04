<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HanhChinhMapping extends Model
{
    protected $table = 'hanh_chinh_mappings';

    protected $fillable = [
        'group_no',
        'xa_phuong_cu_code',
        'xa_phuong_moi_code',
        'new_unit_type',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'group_no' => 'integer',
        ];
    }

    public function xaPhuongCu(): BelongsTo
    {
        return $this->belongsTo(XaPhuongCu::class, 'xa_phuong_cu_code', 'code');
    }

    public function xaPhuongMoi(): BelongsTo
    {
        return $this->belongsTo(XaPhuong::class, 'xa_phuong_moi_code', 'code');
    }
}
