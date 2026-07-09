<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxImportJobRow extends Model
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'import_job_id',
        'row_number',
        'status',
        'ma_so_doanh_nghiep',
        'ten_doanh_nghiep',
        'tax_unit_code',
        'doanh_nghiep_id',
        'tax_unit_id',
        'message',
        'mapped_values',
    ];

    protected function casts(): array
    {
        return [
            'mapped_values' => 'array',
        ];
    }

    public function importJob(): BelongsTo
    {
        return $this->belongsTo(TaxImportJob::class, 'import_job_id');
    }

    public function doanhNghiep(): BelongsTo
    {
        return $this->belongsTo(DoanhNghiep::class);
    }

    public function taxUnit(): BelongsTo
    {
        return $this->belongsTo(TaxUnit::class);
    }
}
