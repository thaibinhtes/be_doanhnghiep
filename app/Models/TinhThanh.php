<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TinhThanh extends Model
{
    protected $table = 'tinh_thanh';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'full_name',
    ];

    public function xaPhuong(): HasMany
    {
        return $this->hasMany(XaPhuong::class, 'tinh_thanh_code', 'code');
    }
}
