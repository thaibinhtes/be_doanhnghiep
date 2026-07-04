<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DnLoaiHinh extends Model
{
    protected $table = 'dn_loai_hinhs';

    protected $fillable = [
        'ma',
        'ten',
        'thu_tu',
        'mac_dinh',
        'is_active',
        'mo_ta',
    ];

    protected function casts(): array
    {
        return [
            'thu_tu' => 'integer',
            'mac_dinh' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function doanhNghieps(): HasMany
    {
        return $this->hasMany(DoanhNghiep::class, 'dn_loai_hinh_id');
    }
}
