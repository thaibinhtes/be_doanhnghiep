<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HanhChinhQuanHuyen extends Model
{
    protected $table = 'hanh_chinh_quan_huyen';

    protected $fillable = [
        'ten',
        'loai',
        'ma',
        'tinh_id',
    ];

    public function tinh(): BelongsTo
    {
        return $this->belongsTo(HanhChinhTinh::class, 'tinh_id');
    }

    public function phuongXas(): HasMany
    {
        return $this->hasMany(HanhChinhPhuongXa::class, 'quan_huyen_id');
    }
}
