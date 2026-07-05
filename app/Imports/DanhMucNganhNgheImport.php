<?php

namespace App\Imports;

use App\Support\DanhMucNganhNgheSyncService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DanhMucNganhNgheImport implements ToCollection, WithHeadingRow
{
    /** @var array{imported: int, updated: int, failed: int, errors: array<int, array{row: int, message: string}>} */
    private array $result = [
        'imported' => 0,
        'updated' => 0,
        'failed' => 0,
        'errors' => [],
    ];

    public function __construct(
        private readonly DanhMucNganhNgheSyncService $syncService,
    ) {}

    public function headingRow(): int
    {
        return 1;
    }

    public function collection(Collection $rows): void
    {
        $payload = [];

        foreach ($rows as $index => $row) {
            $normalized = $this->normalizeRow($row->toArray());
            if ($normalized === null) {
                continue;
            }

            $normalized['row'] = $index + 2;
            $payload[] = $normalized;
        }

        $this->result = $this->syncService->importRows($payload);
    }

    /**
     * @return array{imported: int, updated: int, failed: int, errors: array<int, array{row: int, message: string}>}
     */
    public function getResult(): array
    {
        return $this->result;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function normalizeRow(array $row): ?array
    {
        $cap = $this->pickValue($row, ['cap', 'cấp', 'cap_nganh']);
        $ma = $this->pickValue($row, ['ma', 'mã', 'ma_nganh']);
        $ten = $this->pickValue($row, ['ten', 'tên', 'ten_nganh', 'tên_ngành']);
        $parentMa = $this->pickValue($row, ['parent_ma', 'parentma', 'ma_cha', 'mã_cha']);
        $thuTu = $this->pickValue($row, ['thu_tu', 'thutu', 'thứ_tự']);
        $isActive = $this->pickValue($row, ['is_active', 'isactive', 'hoat_dong', 'trạng_thái']);

        if ($cap === null && $ma === null && $ten === null) {
            return null;
        }

        return [
            'cap' => (int) $cap,
            'ma' => trim((string) $ma),
            'ten' => trim((string) $ten),
            'parent_ma' => trim((string) ($parentMa ?? '')),
            'thu_tu' => $thuTu !== null && $thuTu !== '' ? (int) $thuTu : 0,
            'is_active' => $this->normalizeBoolean($isActive),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function pickValue(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return null;
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return !in_array($normalized, ['0', 'false', 'no', 'n', 'ngung', 'ngừng', 'inactive'], true);
    }
}
