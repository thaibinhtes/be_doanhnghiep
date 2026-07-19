<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HanhChinhTinh extends Model
{
    public const LOAI_CU = 'cu';

    public const LOAI_MOI = 'moi';

    protected $table = 'hanh_chinh_tinh';

    protected $fillable = [
        'ten',
        'loai',
        'ma',
    ];

    public function quanHuyens(): HasMany
    {
        return $this->hasMany(HanhChinhQuanHuyen::class, 'tinh_id');
    }

    public function phuongXas(): HasMany
    {
        return $this->hasMany(HanhChinhPhuongXa::class, 'tinh_id');
    }
}
