<?php

namespace App\Imports;

use App\Models\DoanhNghiep;
use App\Models\DoanhNghiepImportJobRow;
use App\Models\User;
use App\Support\DinhDanhHistoryContext;
use App\Support\DoanhNghiepDinhDanhImportColumnMap;
use App\Support\DoanhNghiepImportRowRecorder;
use App\Support\DoanhNghiepScopeHelper;
use App\Support\DoanhNghiepStatusHelper;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnLimit;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Row;

class DoanhNghiepDinhDanhImport implements OnEachRow, WithChunkReading, WithColumnLimit, WithStartRow
{
    /** @var array<int, array{row: int, message: string}> */
    private array $errors = [];

    private int $updated = 0;

    private int $failed = 0;

    private ?DoanhNghiepImportRowRecorder $rowRecorder = null;

    /** @var array<string, list<string>> */
    private readonly array $columnMap;

    public function __construct(
        private readonly ?User $user = null,
        private readonly int $dataStartRow = DoanhNghiepDinhDanhImportColumnMap::DEFAULT_START_ROW,
        ?array $columnMap = null,
        private readonly ?bool $forcedDinhDanhStatus = null,
        private readonly ?int $importJobId = null,
    ) {
        $this->columnMap = DoanhNghiepDinhDanhImportColumnMap::resolve($columnMap);
        if ($this->importJobId !== null && $this->user !== null) {
            $this->rowRecorder = new DoanhNghiepImportRowRecorder($this->importJobId, $this->user->id);
        }
    }

    public function onRow(Row $row): void
    {
        $rowNumber = $row->getIndex();
        $parsed = DoanhNghiepDinhDanhImportColumnMap::parseRow(
            $row->toArray(null, false, false, $this->endColumn()),
            $this->columnMap,
        );

        if (DoanhNghiepDinhDanhImportColumnMap::isEmptyRow($parsed)) {
            return;
        }

        $msdn = trim((string) ($parsed['maSoDoanhNghiep'] ?? ''));
        $tenDoanhNghiep = trim((string) ($parsed['tenDoanhNghiep'] ?? ''));
        $daCapNhatDinhDanh = $this->forcedDinhDanhStatus;

        if ($msdn === '') {
            $this->recordFailure($rowNumber, null, $tenDoanhNghiep ?: null, 'Thiếu mã số doanh nghiệp.');

            return;
        }

        if (!is_bool($daCapNhatDinhDanh)) {
            $this->recordFailure(
                $rowNumber,
                $msdn,
                $tenDoanhNghiep ?: null,
                'Thiếu trạng thái định danh import. Vui lòng chọn Định danh hoặc Chưa định danh.',
            );

            return;
        }

        $company = DoanhNghiepScopeHelper::query($this->user)
            ->where('ma_so_doanh_nghiep', $msdn)
            ->first();

        if (!$company) {
            $this->recordFailure($rowNumber, $msdn, $tenDoanhNghiep ?: null, "Không tìm thấy doanh nghiệp với MSDN {$msdn}.");

            return;
        }

        DinhDanhHistoryContext::run(
            [
                'nguon' => 'import',
                'ghi_chu' => "Import định danh dòng {$rowNumber}",
            ],
            fn () => DoanhNghiepStatusHelper::syncDinhDanhStatus($company, $daCapNhatDinhDanh),
        );

        $this->updated++;
        $this->rowRecorder?->record(
            $rowNumber,
            DoanhNghiepImportJobRow::STATUS_SUCCESS,
            $msdn,
            $company->ten_doanh_nghiep,
            $company->id,
            'Cập nhật định danh thành công.',
        );
    }

    /**
     * @return array{imported: int, updated: int, failed: int, errors: array<int, array{row: int, message: string}>}
     */
    public function getResult(): array
    {
        $this->rowRecorder?->flush();

        return [
            'imported' => 0,
            'updated' => $this->updated,
            'failed' => $this->failed,
            'errors' => $this->errors,
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function startRow(): int
    {
        return $this->dataStartRow;
    }

    public function endColumn(): string
    {
        return 'Z';
    }

    private function recordFailure(int $rowNumber, ?string $msdn, ?string $tenDoanhNghiep, string $message): void
    {
        $this->failed++;
        $this->errors[] = [
            'row' => $rowNumber,
            'message' => $message,
        ];

        $this->rowRecorder?->record(
            $rowNumber,
            DoanhNghiepImportJobRow::STATUS_FAILED,
            $msdn,
            $tenDoanhNghiep,
            null,
            $message,
        );
    }
}
