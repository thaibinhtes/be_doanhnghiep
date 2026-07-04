<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuanHuyenCu extends Model
{
    protected $table = 'quan_huyen_cu';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'full_name',
        'tinh_thanh_cu_code',
    ];

    public function tinhThanh(): BelongsTo
    {
        return $this->belongsTo(TinhThanhCu::class, 'tinh_thanh_cu_code', 'code');
    }

    public function xaPhuong(): HasMany
    {
        return $this->hasMany(XaPhuongCu::class, 'quan_huyen_cu_code', 'code');
    }
}
