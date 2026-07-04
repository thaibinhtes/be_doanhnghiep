<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DnDinhDanhLichSu extends Model
{
    protected $table = 'dn_dinh_danh_lich_sus';

    protected $fillable = [
        'doanh_nghiep_id',
        'user_id',
        'ma_so_doanh_nghiep',
        'ten_doanh_nghiep',
        'hanh_dong',
        'gia_tri_cu',
        'gia_tri_moi',
        'nguon',
        'ghi_chu',
    ];

    protected function casts(): array
    {
        return [
            'gia_tri_cu' => 'boolean',
            'gia_tri_moi' => 'boolean',
        ];
    }

    public function doanhNghiep(): BelongsTo
    {
        return $this->belongsTo(DoanhNghiep::class, 'doanh_nghiep_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
