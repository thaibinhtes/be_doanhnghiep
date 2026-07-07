<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XaPhuong extends Model
{
    protected $table = 'xa_phuong';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'full_name',
        'unit_type',
        'tinh_thanh_code',
    ];

    public function tinhThanh(): BelongsTo
    {
        return $this->belongsTo(TinhThanh::class, 'tinh_thanh_code', 'code');
    }
}
