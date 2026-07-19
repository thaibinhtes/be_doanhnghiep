<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HanhChinhPhuongXa extends Model
{
    protected $table = 'hanh_chinh_phuong_xa';

    protected $fillable = [
        'ten',
        'loai',
        'ma',
        'quan_huyen_id',
        'tinh_id',
    ];

    public function quanHuyen(): BelongsTo
    {
        return $this->belongsTo(HanhChinhQuanHuyen::class, 'quan_huyen_id');
    }

    public function tinh(): BelongsTo
    {
        return $this->belongsTo(HanhChinhTinh::class, 'tinh_id');
    }
}
