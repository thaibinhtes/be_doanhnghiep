<?php

namespace App\Support;

use App\Models\HopTacXaImportJobRow;
use Illuminate\Support\Facades\Log;

class HopTacXaImportRowRecorder
{
    /** @var list<array<string, mixed>> */
    private array $buffer = [];

    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly int $importJobId,
        private readonly int $userId,
    ) {}

    public function record(
        int $rowNumber,
        string $status,
        ?string $maSoThue,
        ?string $tenHtx,
        ?int $hopTacXaId = null,
        ?string $message = null,
    ): void {
        $now = now();

        $this->buffer[] = [
            'import_job_id' => $this->importJobId,
            'row_number' => $rowNumber,
            'status' => $status,
            'ma_so_thue' => $maSoThue,
            'ten_htx' => $tenHtx,
            'hop_tac_xa_id' => $hopTacXaId,
            'message' => $message,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $this->emitSocketEvent($rowNumber, $status, $maSoThue, $tenHtx, $hopTacXaId, $message);

        if (count($this->buffer) >= self::BATCH_SIZE) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        try {
            HopTacXaImportJobRow::query()->insert($this->buffer);
        } catch (\Throwable $exception) {
            Log::warning('Failed to persist HTX import job rows batch', [
                'import_job_id' => $this->importJobId,
                'error' => $exception->getMessage(),
            ]);
        }

        $this->buffer = [];
    }

    private function emitSocketEvent(
        int $rowNumber,
        string $status,
        ?string $maSoThue,
        ?string $tenHtx,
        ?int $hopTacXaId,
        ?string $message,
    ): void {
        $topic = match ($status) {
            HopTacXaImportJobRow::STATUS_SUCCESS => ImportSocketTopics::EXCEL_ROW_SUCCESS,
            HopTacXaImportJobRow::STATUS_DUPLICATE => ImportSocketTopics::EXCEL_ROW_DUPLICATE,
            default => ImportSocketTopics::EXCEL_ROW_FAILED,
        };

        ImportSocketNotifier::notify(
            $this->userId,
            $topic,
            $this->importJobId,
            [
                'row' => $rowNumber,
                'status' => $status,
                'maSoThue' => $maSoThue,
                'tenHtx' => $tenHtx,
                'hopTacXaId' => $hopTacXaId,
                'message' => $message,
                'entity' => 'hop-tac-xa',
            ],
        );
    }
}
