<?php

namespace App\Support;

use App\Models\DoanhNghiepImportJobRow;
use Illuminate\Support\Facades\Log;

class DoanhNghiepImportRowRecorder
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
        ?string $maSoDoanhNghiep,
        ?string $tenDoanhNghiep,
        ?int $doanhNghiepId = null,
        ?string $message = null,
    ): void {
        $now = now();

        $this->buffer[] = [
            'import_job_id' => $this->importJobId,
            'row_number' => $rowNumber,
            'status' => $status,
            'ma_so_doanh_nghiep' => $maSoDoanhNghiep,
            'ten_doanh_nghiep' => $tenDoanhNghiep,
            'doanh_nghiep_id' => $doanhNghiepId,
            'message' => $message,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $this->emitSocketEvent($rowNumber, $status, $maSoDoanhNghiep, $tenDoanhNghiep, $doanhNghiepId, $message);

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
            DoanhNghiepImportJobRow::query()->insert($this->buffer);
        } catch (\Throwable $exception) {
            Log::warning('Failed to persist import job rows batch', [
                'import_job_id' => $this->importJobId,
                'error' => $exception->getMessage(),
            ]);
        }

        $this->buffer = [];
    }

    private function emitSocketEvent(
        int $rowNumber,
        string $status,
        ?string $maSoDoanhNghiep,
        ?string $tenDoanhNghiep,
        ?int $doanhNghiepId,
        ?string $message,
    ): void {
        $topic = match ($status) {
            DoanhNghiepImportJobRow::STATUS_SUCCESS => ImportSocketTopics::EXCEL_ROW_SUCCESS,
            DoanhNghiepImportJobRow::STATUS_DUPLICATE => ImportSocketTopics::EXCEL_ROW_DUPLICATE,
            default => ImportSocketTopics::EXCEL_ROW_FAILED,
        };

        ImportSocketNotifier::notify(
            $this->userId,
            $topic,
            $this->importJobId,
            [
                'row' => $rowNumber,
                'status' => $status,
                'maSoDoanhNghiep' => $maSoDoanhNghiep,
                'tenDoanhNghiep' => $tenDoanhNghiep,
                'doanhNghiepId' => $doanhNghiepId,
                'message' => $message,
                'entity' => 'doanh-nghiep',
            ],
        );
    }
}
