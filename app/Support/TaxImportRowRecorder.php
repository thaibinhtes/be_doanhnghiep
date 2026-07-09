<?php

namespace App\Support;

use App\Models\TaxImportJobRow;
use Illuminate\Support\Facades\Log;

class TaxImportRowRecorder
{
    /** @var list<array<string, mixed>> */
    private array $buffer = [];

    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly int $importJobId,
        private readonly int $userId,
        private readonly string $entity = 'company-tax',
    ) {}

    public function record(
        int $rowNumber,
        string $status,
        ?string $maSoDoanhNghiep,
        ?string $tenDoanhNghiep,
        ?string $taxUnitCode = null,
        ?int $doanhNghiepId = null,
        ?int $taxUnitId = null,
        ?string $message = null,
        ?array $mappedValues = null,
    ): void {
        $now = now();

        $this->buffer[] = [
            'import_job_id' => $this->importJobId,
            'row_number' => $rowNumber,
            'status' => $status,
            'ma_so_doanh_nghiep' => $maSoDoanhNghiep,
            'ten_doanh_nghiep' => $tenDoanhNghiep,
            'tax_unit_code' => $taxUnitCode,
            'doanh_nghiep_id' => $doanhNghiepId,
            'tax_unit_id' => $taxUnitId,
            'message' => $message,
            'mapped_values' => $mappedValues !== null ? json_encode($mappedValues, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $this->emitSocketEvent(
            $rowNumber,
            $status,
            $maSoDoanhNghiep,
            $tenDoanhNghiep,
            $doanhNghiepId,
            $message,
        );

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
            TaxImportJobRow::query()->insert($this->buffer);
        } catch (\Throwable $exception) {
            Log::warning('Failed to persist tax import job rows batch', [
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
            TaxImportJobRow::STATUS_SUCCESS => ImportSocketTopics::EXCEL_ROW_SUCCESS,
            TaxImportJobRow::STATUS_DUPLICATE => ImportSocketTopics::EXCEL_ROW_DUPLICATE,
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
                'entity' => $this->entity,
            ],
        );
    }
}
