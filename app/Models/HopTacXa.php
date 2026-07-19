<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HopTacXa extends Model
{
    protected $table = 'hop_tac_xas';

    protected $fillable = [
        'tt',
        'ten_htx',
        'ma_so_thue',
        'nam_thanh_lap',
        'chu_tich_hdqt_ten',
        'dien_thoai',
        'dia_chi',
        'dia_chi_cu',
        'phuong_xa',
        'xa_phuong_cu',
        'xa_phuong_moi',
        'quan_huyen_cu',
        'quan_huyen_moi',
        'tinh_thanh_cu',
        'dien_tich_ha',
        'von_dieu_le',
        'so_thanh_vien',
        'so_nguoi_lao_dong',
        'linh_vuc',
        'hoat_dong',
        'ds_thanh_vien',
        'dia_chi_moi',
        'ghi_chu',
        'don_vi_id',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'tt' => 'integer',
            'so_thanh_vien' => 'integer',
            'so_nguoi_lao_dong' => 'integer',
            'dien_tich_ha' => 'float',
        ];
    }

    public function donVi(): BelongsTo
    {
        return $this->belongsTo(DonVi::class, 'don_vi_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function taxManagement(): HasOne
    {
        return $this->hasOne(CooperativeTaxManagement::class, 'hop_tac_xa_id');
    }
}
