<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HopTacXaImportJobRow extends Model
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'import_job_id',
        'row_number',
        'status',
        'ma_so_thue',
        'ten_htx',
        'hop_tac_xa_id',
        'message',
    ];

    public function importJob(): BelongsTo
    {
        return $this->belongsTo(HopTacXaImportJob::class, 'import_job_id');
    }

    public function hopTacXa(): BelongsTo
    {
        return $this->belongsTo(HopTacXa::class, 'hop_tac_xa_id');
    }
}
