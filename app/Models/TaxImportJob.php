<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxImportJob extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const TYPE_TAX_UNITS = 'tax_units';
    public const TYPE_COMPANY_TAX = 'company_tax';

    protected $fillable = [
        'user_id',
        'status',
        'type',
        'file_path',
        'original_filename',
        'start_row',
        'tax_paid_at',
        'column_map',
        'result',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'column_map' => 'array',
            'result' => 'array',
            'tax_paid_at' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(TaxImportJobRow::class, 'import_job_id');
    }

    public function markProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
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
