<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DnTrangThai extends Model
{
    protected $table = 'dn_trang_thais';

    protected $fillable = [
        'ma',
        'ten',
        'loai',
        'yeu_cau_ly_do',
        'hien_thi_bao_cao',
        'thu_tu_bao_cao',
        'mac_dinh',
        'is_active',
        'mo_ta',
    ];

    protected function casts(): array
    {
        return [
            'yeu_cau_ly_do' => 'boolean',
            'hien_thi_bao_cao' => 'boolean',
            'mac_dinh' => 'boolean',
            'is_active' => 'boolean',
            'thu_tu_bao_cao' => 'integer',
        ];
    }

    public function doanhNghieps(): HasMany
    {
        return $this->hasMany(DoanhNghiep::class, 'dn_trang_thai_id');
    }
}
