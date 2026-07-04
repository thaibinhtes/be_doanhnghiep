<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TinhThanhCu extends Model
{
    protected $table = 'tinh_thanh_cu';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'full_name',
    ];

    public function quanHuyen(): HasMany
    {
        return $this->hasMany(QuanHuyenCu::class, 'tinh_thanh_cu_code', 'code');
    }
}
