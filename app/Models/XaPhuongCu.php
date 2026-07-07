<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class XaPhuongCu extends Model
{
    protected $table = 'xa_phuong_cu';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'full_name',
        'unit_type',
        'quan_huyen_cu_code',
    ];

    public function quanHuyen(): BelongsTo
    {
        return $this->belongsTo(QuanHuyenCu::class, 'quan_huyen_cu_code', 'code');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(HanhChinhMapping::class, 'xa_phuong_cu_code', 'code');
    }
}
