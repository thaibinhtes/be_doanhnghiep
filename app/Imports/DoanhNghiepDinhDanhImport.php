<?php

namespace App\Imports;

use App\Models\DoanhNghiep;
use App\Models\DoanhNghiepImportJobRow;
use App\Models\HopTacXa;
use App\Models\User;
use App\Support\DinhDanhHistoryContext;
use App\Support\DoanhNghiepDinhDanhImportColumnMap;
use App\Support\DoanhNghiepImportColumnMap;
use App\Support\DoanhNghiepImportRowRecorder;
use App\Support\DoanhNghiepScopeHelper;
use App\Support\DoanhNghiepStatusHelper;
use App\Support\HopTacXaScopeHelper;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnLimit;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Row;

/**
 * Import định danh: chỉ đối chiếu theo 1 field + ghi ngày định danh.
 * Không tạo DN mới, không cập nhật tên/địa chỉ/trạng thái hoạt động.
 */
class DoanhNghiepDinhDanhImport implements OnEachRow, WithChunkReading, WithColumnLimit, WithStartRow
{
    /** @var array<int, array{row: int, message: string}> */
    private array $errors = [];

    private int $updated = 0;

    private int $failed = 0;

    private ?DoanhNghiepImportRowRecorder $rowRecorder = null;

    /** @var array<string, list<string>> */
    private readonly array $columnMap;

    private readonly string $lookupField;

    private readonly string $endColumn;

    public function __construct(
        private readonly ?User $user = null,
        private readonly int $dataStartRow = DoanhNghiepDinhDanhImportColumnMap::DEFAULT_START_ROW,
        ?array $columnMap = null,
        private readonly ?bool $forcedDinhDanhStatus = null,
        private readonly ?int $importJobId = null,
        private readonly ?string $defaultIdentityDate = null,
        ?string $lookupField = null,
    ) {
        $this->lookupField = $lookupField && DoanhNghiepDinhDanhImportColumnMap::isLookupField($lookupField)
            ? $lookupField
            : DoanhNghiepDinhDanhImportColumnMap::DEFAULT_LOOKUP_FIELD;
        $this->columnMap = DoanhNghiepDinhDanhImportColumnMap::resolve($columnMap, $this->lookupField);
        DoanhNghiepDinhDanhImportColumnMap::assertValid($this->columnMap, $this->lookupField);
        $this->endColumn = DoanhNghiepImportColumnMap::resolveEndColumn($this->columnMap, 'Z');
        if ($this->importJobId !== null && $this->user !== null) {
            $this->rowRecorder = new DoanhNghiepImportRowRecorder($this->importJobId, $this->user->id);
        }
    }

    public function onRow(Row $row): void
    {
        $rowNumber = $row->getIndex();
        $parsed = DoanhNghiepDinhDanhImportColumnMap::parseExcelRow($row, $this->columnMap);

        if (DoanhNghiepDinhDanhImportColumnMap::isEmptyRow($parsed, $this->lookupField)) {
            return;
        }

        $lookupValue = trim((string) ($parsed[$this->lookupField] ?? ''));
        $daCapNhatDinhDanh = $this->forcedDinhDanhStatus;
        $identityDate = DoanhNghiepDinhDanhImportColumnMap::resolveIdentityDate(
            $parsed[DoanhNghiepDinhDanhImportColumnMap::DATE_FIELD] ?? null,
            null,
        );

        if ($lookupValue === '') {
            $this->recordFailure($rowNumber, null, null, 'Thiếu giá trị cột đối chiếu.');

            return;
        }

        if (! is_bool($daCapNhatDinhDanh)) {
            $this->recordFailure(
                $rowNumber,
                $lookupValue,
                null,
                'Thiếu trạng thái định danh. Vui lòng chọn Định danh hoặc Chưa định danh.',
            );

            return;
        }

        $dbColumn = DoanhNghiepDinhDanhImportColumnMap::lookupDbColumn($this->lookupField);
        if ($dbColumn === null) {
            $this->recordFailure($rowNumber, $lookupValue, null, 'Trường đối chiếu không hợp lệ.');

            return;
        }

        $company = DoanhNghiepScopeHelper::query($this->user)
            ->where($dbColumn, $lookupValue)
            ->first();

        if ($company) {
            $this->applyDoanhNghiep($rowNumber, $company, $lookupValue, $daCapNhatDinhDanh, $identityDate);

            return;
        }

        // Chỉ thử HTX khi đối chiếu theo mã số (MST / MSDN).
        if ($this->lookupField === 'maSoDoanhNghiep') {
            $htx = HopTacXaScopeHelper::query($this->user)
                ->where('ma_so_thue', $lookupValue)
                ->first();

            if ($htx) {
                $this->applyHopTacXa($rowNumber, $htx, $lookupValue, $daCapNhatDinhDanh, $identityDate);

                return;
            }
        }

        $label = DoanhNghiepDinhDanhImportColumnMap::LOOKUP_FIELDS[$this->lookupField] ?? $this->lookupField;
        $this->recordFailure(
            $rowNumber,
            $lookupValue,
            null,
            "Không tìm thấy DN/HTX với {$label} = {$lookupValue}.",
        );
    }

    private function applyDoanhNghiep(
        int $rowNumber,
        DoanhNghiep $company,
        string $lookupValue,
        bool $daCapNhatDinhDanh,
        \Carbon\Carbon $identityDate,
    ): void {
        DinhDanhHistoryContext::run(
            [
                'nguon' => 'import',
                'ghi_chu' => "Import định danh dòng {$rowNumber}",
                'thoi_diem' => $identityDate,
            ],
            fn () => DoanhNghiepStatusHelper::syncDinhDanhStatus($company, $daCapNhatDinhDanh),
        );

        $this->updated++;
        $this->rowRecorder?->record(
            $rowNumber,
            DoanhNghiepImportJobRow::STATUS_SUCCESS,
            $company->ma_so_doanh_nghiep ?: $lookupValue,
            $company->ten_doanh_nghiep,
            $company->id,
            'Định danh DN OK ('.$identityDate->format('d/m/Y H:i').') — không sửa thông tin DN.',
        );
    }

    private function applyHopTacXa(
        int $rowNumber,
        HopTacXa $htx,
        string $lookupValue,
        bool $daCapNhatDinhDanh,
        \Carbon\Carbon $identityDate,
    ): void {
        DinhDanhHistoryContext::run(
            [
                'nguon' => 'import',
                'ghi_chu' => "Import định danh HTX dòng {$rowNumber}",
                'thoi_diem' => $identityDate,
            ],
            fn () => $htx->update(['da_cap_nhat_dinh_danh' => $daCapNhatDinhDanh]),
        );

        $this->updated++;
        $this->rowRecorder?->record(
            $rowNumber,
            DoanhNghiepImportJobRow::STATUS_SUCCESS,
            $htx->ma_so_thue ?: $lookupValue,
            $htx->ten_htx,
            null,
            'Định danh HTX OK ('.$identityDate->format('d/m/Y H:i').') — không sửa thông tin HTX khác.',
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
        return $this->endColumn;
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
