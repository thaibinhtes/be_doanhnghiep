<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DoanhNghiep extends Model
{
    use HasFactory;

    protected $table = 'doanh_nghieps';

    protected $fillable = [
        'tt',
        'ma_so_doanh_nghiep',
        'ten_doanh_nghiep',
        'dia_chi',
        'dia_chi_cu',
        'dia_chi_moi',
        'long',
        'lat',
        'quan_huyen',
        'phuong_xa',
        'tinh_thanh_cu_code',
        'quan_huyen_cu_code',
        'xa_phuong_cu_code',
        'tinh_thanh_code',
        'xa_phuong_code',
        'ghi_chu_hanh_chinh',
        'hanh_chinh_synced_at',
        'von_dieu_le',
        'trang_thai',
        'dn_trang_thai_id',
        'ly_do_trang_thai',
        'da_cap_nhat_dinh_danh',
        'dien_thoai',
        'nguoi_dai_dien_ten',
        'ngay_sinh_nguoi_dai_dien',
        'chu_so_huu_ten',
        'nguoi_dai_dien_id',
        'chu_so_huu_id',
        'ds_co_dong',
        'nganh_nghe_kd_chinh',
        'nganh_nghe_kd',
        'ngay_cap',
        'ngay_dang_ky_thay_doi',
        'loai_hinh_dn',
        'dn_loai_hinh_id',
        'so_luong_lao_dong',
        'loai_dn',
        'don_vi_id',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'tt' => 'integer',
            'so_luong_lao_dong' => 'integer',
            'da_cap_nhat_dinh_danh' => 'boolean',
            'hanh_chinh_synced_at' => 'datetime',
            'long' => 'float',
            'lat' => 'float',
            'nganh_nghe_kd' => 'array',
        ];
    }

    public function nganhNgheKdChinh(): BelongsTo
    {
        return $this->belongsTo(DanhMucNganhNghe::class, 'nganh_nghe_kd_chinh', 'ma');
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

    public function dnTrangThai(): BelongsTo
    {
        return $this->belongsTo(DnTrangThai::class, 'dn_trang_thai_id');
    }

    public function dnLoaiHinh(): BelongsTo
    {
        return $this->belongsTo(DnLoaiHinh::class, 'dn_loai_hinh_id');
    }

    public function dinhDanhLichSu(): HasMany
    {
        return $this->hasMany(DnDinhDanhLichSu::class, 'doanh_nghiep_id')->latest();
    }

    /** Đơn vị trực thuộc quản lý doanh nghiệp. */
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
        return $this->hasOne(CompanyTaxManagement::class, 'doanh_nghiep_id');
    }

    public function quanHuyenCu(): BelongsTo
    {
        return $this->belongsTo(QuanHuyenCu::class, 'quan_huyen_cu_code', 'code');
    }

    public function tinhThanhCu(): BelongsTo
    {
        return $this->belongsTo(TinhThanhCu::class, 'tinh_thanh_cu_code', 'code');
    }

    public function xaPhuongCu(): BelongsTo
    {
        return $this->belongsTo(XaPhuongCu::class, 'xa_phuong_cu_code', 'code');
    }

    public function tinhThanh(): BelongsTo
    {
        return $this->belongsTo(TinhThanh::class, 'tinh_thanh_code', 'code');
    }

    public function xaPhuong(): BelongsTo
    {
        return $this->belongsTo(XaPhuong::class, 'xa_phuong_code', 'code');
    }
}
