<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DoanhNghiep extends Model
{
    use HasFactory;

    protected $table = 'doanh_nghieps';

    protected $fillable = [
        'tt',
        'ma_so_doanh_nghiep',
        'ten_doanh_nghiep',
        'dia_chi',
        'quan_huyen',
        'phuong_xa',
        'von_dieu_le',
        'trang_thai',
        'dien_thoai',
        'nguoi_dai_dien',
        'ngay_sinh_nguoi_dai_dien',
        'chu_so_huu',
        'nganh_nghe_kd_chinh',
        'nganh_nghe_kd',
        'ngay_cap',
        'ngay_dang_ky_thay_doi',
        'loai_hinh_dn',
        'so_luong_lao_dong',
        'ds_thanh_vien_gop_von',
        'ds_co_dong',
        'loai_dn',
    ];

    protected function casts(): array
    {
        return [
            'tt' => 'integer',
            'so_luong_lao_dong' => 'integer',
        ];
    }

    /**
     * DoanhNghiep belongs to many Members.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'member_companies');
    }
}
