<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoanhNghiepImportJobRow extends Model
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
        'doanh_nghiep_id',
        'message',
    ];

    public function importJob(): BelongsTo
    {
        return $this->belongsTo(DoanhNghiepImportJob::class, 'import_job_id');
    }

    public function doanhNghiep(): BelongsTo
    {
        return $this->belongsTo(DoanhNghiep::class);
    }
}
