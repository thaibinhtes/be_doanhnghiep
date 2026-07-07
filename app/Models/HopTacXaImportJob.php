<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HopTacXaImportJob extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const TYPE_COOPERATIVES = 'cooperatives';

    protected $fillable = [
        'user_id',
        'don_vi_id',
        'status',
        'type',
        'file_path',
        'original_filename',
        'start_row',
        'column_map',
        'value_extensions',
        'use_column_map',
        'result',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'column_map' => 'array',
            'value_extensions' => 'array',
            'result' => 'array',
            'use_column_map' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function donVi(): BelongsTo
    {
        return $this->belongsTo(DonVi::class, 'don_vi_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(HopTacXaImportJobRow::class, 'import_job_id');
    }

    public function markProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    /**
     * @param  array{imported: int, duplicates?: int, updated?: int, failed: int, errors: array<int, array{row: int, message: string}>}  $result
     */
    public function markCompleted(array $result): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'result' => $result,
            'error_message' => null,
            'finished_at' => now(),
        ]);
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $message,
            'finished_at' => now(),
        ]);
    }
}
