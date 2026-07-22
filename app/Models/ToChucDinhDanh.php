<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToChucDinhDanh extends Model
{
    public const LOAI_DOANH_NGHIEP = 'doanh_nghiep';

    public const LOAI_HOP_TAC_XA = 'hop_tac_xa';

    protected $table = 'to_chuc_dinh_danhs';

    protected $fillable = [
        'loai_to_chuc',
        'ma_so',
        'ten_to_chuc',
        'doanh_nghiep_id',
        'hop_tac_xa_id',
        'thoi_gian_dinh_danh',
        'da_dinh_danh',
        'user_id',
        'nguon',
        'ghi_chu',
    ];

    protected function casts(): array
    {
        return [
            'thoi_gian_dinh_danh' => 'datetime',
            'da_dinh_danh' => 'boolean',
        ];
    }

    public function doanhNghiep(): BelongsTo
    {
        return $this->belongsTo(DoanhNghiep::class, 'doanh_nghiep_id');
    }

    public function hopTacXa(): BelongsTo
    {
        return $this->belongsTo(HopTacXa::class, 'hop_tac_xa_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
