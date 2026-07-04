<?php

namespace App\Support;

class DoanhNghiepImportColumnMap
{
    /**
     * Template Excel do các đơn vị cung cấp (header hàng 12, data từ hàng 13).
     *
     * @var array<string, list<string>>
     */
    public const UNIT_TEMPLATE = [
        'tt' => ['B'],
        'maSoDoanhNghiep' => ['C'],
        'tenDoanhNghiep' => ['D', 'E', 'F', 'G'],
        'diaChi' => ['H', 'I', 'J', 'K', 'L', 'M', 'N'],
        'quanHuyen' => ['O', 'P', 'Q'],
        'phuongXa' => ['R', 'S', 'T'],
        'vonDieuLe' => ['U', 'V', 'W'],
        'trangThai' => ['X', 'Y', 'Z'],
    ];

    public const DEFAULT_START_ROW = 13;

    /**
     * Format ánh xạ template SKHĐT / đơn vị (raw — có thể chứa vùng cột AA-AB).
     *
     * @var array<string, list<string>>
     */
    public const SKHDT_FORMAT_COLUMN_MAP = [
        'tt' => ['B'],
        'maSoDoanhNghiep' => ['C'],
        'tenDoanhNghiep' => ['D', 'E', 'F', 'G'],
        'diaChi' => ['H', 'I', 'J', 'K', 'L', 'M', 'N'],
        'quanHuyen' => ['O', 'P'],
        'phuongXa' => ['Q', 'R', 'S', 'T', 'U'],
        'vonDieuLe' => ['V', 'W', 'X'],
        'trangThai' => ['Y', 'Z'],
        'dienThoai' => ['AA-AB'],
        'nguoiDaiDienTen' => ['AC-AE'],
        'ngaySinhNguoiDaiDien' => ['AF-AH'],
        'chuSoHuuTen' => ['AI'],
        'nganhNgheKDChinh' => ['AJ'],
        'nganhNgheKD' => ['AK'],
        'ngayCap' => ['AL'],
        'ngayDangKyThayDoi' => ['AM'],
        'loaiHinhDN' => ['AN'],
        'soLuongLaoDong' => ['AO'],
        'dsThanhVienGopVon' => ['AP'],
        'dsCoDong' => ['AQ'],
        'loaiDN' => ['AR'],
    ];

    public const SKHDT_FORMAT_VALUE_EXTENSIONS = [
        'nganhNgheKDChinh' => 'vsic_code',
        'nganhNgheKD' => 'vsic_code_list',
    ];

    /**
     * @return array{start_row: int, column_map: array<string, list<string>>, value_extensions: array<string, string>}
     */
    public static function defaultSkhdtFormat(): array
    {
        return [
            'start_row' => self::DEFAULT_START_ROW,
            'column_map' => self::normalizeStoredColumnMap(self::SKHDT_FORMAT_COLUMN_MAP),
            'value_extensions' => self::SKHDT_FORMAT_VALUE_EXTENSIONS,
        ];
    }

    /**
     * Chuẩn hóa column_map trước khi lưu DB (expand AA-AB → AA, AB).
     *
     * @param  array<string, mixed>  $columnMap
     * @return array<string, list<string>>
     */
    public static function normalizeStoredColumnMap(array $columnMap): array
    {
        $normalized = [];

        foreach ($columnMap as $key => $columns) {
            if (!is_string($key)) {
                continue;
            }

            $list = is_array($columns) ? $columns : [(string) $columns];
            $letters = self::expandColumnLetters(array_map('strval', $list));

            if ($letters !== []) {
                $normalized[$key] = $letters;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $customMap
     * @return array<string, list<string>>
     */
    public static function resolve(?array $customMap = null): array
    {
        if ($customMap === null || $customMap === []) {
            return self::UNIT_TEMPLATE;
        }

        $resolved = [];

        foreach ($customMap as $key => $columns) {
            if (!is_string($key) || !is_array($columns)) {
                continue;
            }

            $letters = self::expandColumnLetters($columns);

            if ($letters !== []) {
                $resolved[$key] = $letters;
            }
        }

        return $resolved;
    }

    /**
     * @param  list<string|int>  $columns
     * @return list<string>
     */
    public static function expandColumnLetters(array $columns): array
    {
        $expanded = [];

        foreach ($columns as $column) {
            $expanded = array_merge($expanded, self::expandColumnSpec((string) $column));
        }

        return array_values(array_unique($expanded));
    }

    /**
     * @return list<string>
     */
    public static function expandColumnSpec(string $spec): array
    {
        $spec = strtoupper(trim($spec));

        if ($spec === '') {
            return [];
        }

        if (str_contains($spec, ',')) {
            $result = [];
            foreach (explode(',', $spec) as $part) {
                $result = array_merge($result, self::expandColumnSpec(trim($part)));
            }

            return $result;
        }

        if (preg_match('/^([A-Z]+)-([A-Z]+)$/', $spec, $matches)) {
            $startIdx = self::columnLetterToIndex($matches[1]);
            $endIdx = self::columnLetterToIndex($matches[2]);

            if ($startIdx < 0 || $endIdx < 0) {
                return [];
            }

            if ($startIdx > $endIdx) {
                [$startIdx, $endIdx] = [$endIdx, $startIdx];
            }

            $cols = [];
            for ($i = $startIdx; $i <= $endIdx; $i++) {
                $cols[] = self::columnIndexToLetter($i);
            }

            return $cols;
        }

        $letter = self::normalizeColumnLetter($spec);

        return $letter !== '' ? [$letter] : [];
    }

    /**
     * @param  array<int, mixed>  $row  Zero-based row from Maatwebsite (A = 0)
     * @param  array<string, list<string>>  $columnMap
     * @return array<string, mixed>
     */
    public static function parseRow(array $row, array $columnMap): array
    {
        $result = [];

        foreach ($columnMap as $key => $columns) {
            if ($columns === []) {
                continue;
            }

            $rawValue = self::readColumns($row, $columns);

            if ($rawValue === null) {
                continue;
            }

            $value = DoanhNghiepExcelColumns::normalizeImportValue($key, $rawValue);

            if ($value === null || $value === '') {
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function isEmptyRow(array $data): bool
    {
        $primaryKeys = ['maSoDoanhNghiep', 'tenDoanhNghiep'];

        foreach ($primaryKeys as $key) {
            $value = $data[$key] ?? null;
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        $meaningful = array_filter($data, static fn ($value) => $value !== null && $value !== '');

        return empty($meaningful);
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  list<string>  $columns
     */
    private static function readColumns(array $row, array $columns): ?string
    {
        foreach ($columns as $column) {
            $index = self::columnLetterToIndex($column);

            if ($index < 0) {
                continue;
            }

            $value = $row[$index] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $text = trim((string) $value);

            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    private static function normalizeColumnLetter(string $column): string
    {
        return strtoupper(preg_replace('/[^A-Za-z]/', '', $column) ?? '');
    }

    /** Excel column letter to 0-based index (A = 0, AA = 26). */
    private static function columnLetterToIndex(string $letter): int
    {
        $letter = self::normalizeColumnLetter($letter);

        if ($letter === '') {
            return -1;
        }

        $index = 0;

        for ($i = 0, $len = strlen($letter); $i < $len; $i++) {
            $index = ($index * 26) + (ord($letter[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }

    /** 0-based index to Excel column letter. */
    private static function columnIndexToLetter(int $index): string
    {
        $columnIndex = $index + 1;
        $letter = '';

        while ($columnIndex > 0) {
            $columnIndex--;
            $letter = chr(65 + ($columnIndex % 26)) . $letter;
            $columnIndex = intdiv($columnIndex, 26);
        }

        return $letter;
    }
}
