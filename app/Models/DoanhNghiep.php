<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'nguoi_dai_dien_id',
        'chu_so_huu_id',
        'nganh_nghe_kd_chinh',
        'nganh_nghe_kd',
        'ngay_cap',
        'ngay_dang_ky_thay_doi',
        'loai_hinh_dn',
        'so_luong_lao_dong',
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
     * DoanhNghiep belongs to many Members with pivot data.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'member_companies')
            ->withPivot('date_join', 'position', 'investment_amount')
            ->withTimestamps();
    }

    /**
     * Member companies as a hasMany for direct access.
     */
    public function memberCompanies()
    {
        return $this->hasMany(MemberCompany::class, 'doanh_nghiep_id');
    }

    /**
     * Chu so huu (owner) belongs to a Member.
     */
    public function chuSoHuu(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'chu_so_huu_id');
    }

    /**
     * Nguoi dai dien (legal representative) belongs to a Member.
     */
    public function nguoiDaiDien(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'nguoi_dai_dien_id');
    }
}
