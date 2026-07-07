<?php

namespace App\Support;

class HanhChinhImportColumnMap
{
    public const DEFAULT_START_ROW = 2;

    public const EXAMPLE_CONFIG_CODE = 'hanh_chinh_mapping_example';

    public const LEGACY_ONLY_CONFIG_CODE = 'hanh_chinh_legacy_only';

    public const NEW_ONLY_CONFIG_CODE = 'hanh_chinh_new_only';

    public const NEW_FROM_MAPPING_CONFIG_CODE = 'hanh_chinh_new_from_mapping';

    public const IMPORT_END_COLUMN = 'G';

    public const NEW_IMPORT_END_COLUMN = 'G';

    /**
     * File mapping đầy đủ: STT, huyện, xã cũ, loại cũ, đơn vị mới, loại mới.
     *
     * @var array<string, list<string>>
     */
    public const DEFAULT_COLUMN_MAP = [
        'stt' => ['A'],
        'quanHuyenCu' => ['B'],
        'xaPhuongCu' => ['C'],
        'loaiCu' => ['D'],
        'xaPhuongMoi' => ['F'],
        'loaiMoi' => ['G'],
    ];

    /**
     * Chỉ import đơn vị hành chính cũ (3 cột).
     *
     * @var array<string, list<string>>
     */
    public const LEGACY_ONLY_COLUMN_MAP = [
        'quanHuyenCu' => ['A'],
        'xaPhuongCu' => ['B'],
        'loaiCu' => ['C'],
    ];

    /**
     * Chỉ import đơn vị hành chính mới (2 cột).
     *
     * @var array<string, list<string>>
     */
    public const NEW_ONLY_COLUMN_MAP = [
        'xaPhuongMoi' => ['A'],
        'loaiMoi' => ['B'],
    ];

    /**
     * Import đơn vị mới từ file mapping đầy đủ (cột F/G).
     *
     * @var array<string, list<string>>
     */
    public const NEW_FROM_MAPPING_COLUMN_MAP = [
        'xaPhuongMoi' => ['F'],
        'loaiMoi' => ['G'],
    ];

    /**
     * @return array{start_row: int, column_map: array<string, list<string>>, value_extensions: array<string, string>}
     */
    public static function defaultExampleFormat(): array
    {
        return [
            'start_row' => self::DEFAULT_START_ROW,
            'column_map' => self::normalizeStoredColumnMap(self::DEFAULT_COLUMN_MAP),
            'value_extensions' => [],
        ];
    }

    /**
     * @return array{start_row: int, column_map: array<string, list<string>>, value_extensions: array<string, string>}
     */
    public static function legacyOnlyExampleFormat(): array
    {
        return [
            'start_row' => self::DEFAULT_START_ROW,
            'column_map' => self::normalizeStoredColumnMap(self::LEGACY_ONLY_COLUMN_MAP),
            'value_extensions' => [],
        ];
    }

    /**
     * @return array{start_row: int, column_map: array<string, list<string>>, value_extensions: array<string, string>}
     */
    public static function newOnlyExampleFormat(): array
    {
        return [
            'start_row' => self::DEFAULT_START_ROW,
            'column_map' => self::normalizeStoredColumnMap(self::NEW_ONLY_COLUMN_MAP),
            'value_extensions' => [],
        ];
    }

    /**
     * @return array{start_row: int, column_map: array<string, list<string>>, value_extensions: array<string, string>}
     */
    public static function newFromMappingExampleFormat(): array
    {
        return [
            'start_row' => self::DEFAULT_START_ROW,
            'column_map' => self::normalizeStoredColumnMap(self::NEW_FROM_MAPPING_COLUMN_MAP),
            'value_extensions' => [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function forwardFillRows(array $rows): array
    {
        $carry = [
            'stt' => null,
            'groupNo' => null,
            'quanHuyenCu' => '',
            'xaPhuongMoi' => '',
            'loaiMoi' => '',
        ];

        $filled = [];

        foreach ($rows as $row) {
            if (empty($row['groupNo']) && !empty($row['stt'])) {
                $row['groupNo'] = $row['stt'];
            }

            foreach (['stt', 'groupNo', 'quanHuyenCu', 'xaPhuongMoi', 'loaiMoi'] as $key) {
                $value = $row[$key] ?? null;
                if (($value === null || $value === '') && ($carry[$key] ?? '') !== '' && $carry[$key] !== null) {
                    $row[$key] = $carry[$key];
                }
                if (isset($row[$key]) && $row[$key] !== '' && $row[$key] !== null) {
                    $carry[$key] = $row[$key];
                }
            }

            if (empty($row['groupNo']) && $carry['groupNo'] !== null) {
                $row['groupNo'] = $carry['groupNo'];
            }

            if (empty($row['groupNo']) && $carry['stt'] !== null) {
                $row['groupNo'] = $carry['stt'];
            }

            $filled[] = $row;
        }

        return $filled;
    }

    /**
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
            return self::DEFAULT_COLUMN_MAP;
        }

        return self::normalizeStoredColumnMap($customMap);
    }

    /**
     * @param  array<string, mixed>|null  $customMap
     * @return array<string, list<string>>
     */
    public static function resolveNew(?array $customMap = null): array
    {
        if ($customMap === null || $customMap === []) {
            return self::NEW_FROM_MAPPING_COLUMN_MAP;
        }

        $normalized = self::normalizeStoredColumnMap($customMap);

        foreach (self::NEW_FROM_MAPPING_COLUMN_MAP as $key => $columns) {
            if (!isset($normalized[$key]) || $normalized[$key] === []) {
                $normalized[$key] = $columns;
            }
        }

        return $normalized;
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
     * @param  array<int, mixed>  $row
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

            $value = HanhChinhExcelColumns::normalizeImportValue($key, $rawValue);
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
        $keys = ['quanHuyenCu', 'xaPhuongCu'];
        foreach ($keys as $key) {
            if (($data[$key] ?? null) !== null && ($data[$key] ?? '') !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function isEmptyNewRow(array $data): bool
    {
        return ($data['xaPhuongMoi'] ?? null) === null || ($data['xaPhuongMoi'] ?? '') === '';
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  list<string>  $columns
     */
    private static function readColumns(array $row, array $columns): ?string
    {
        $offset = self::rowIndexOffset($row);

        foreach ($columns as $column) {
            $index = self::columnLetterToIndex($column);
            if ($index < 0) {
                continue;
            }

            $candidateIndex = $index + $offset;
            if (!array_key_exists($candidateIndex, $row)) {
                continue;
            }

            $value = $row[$candidateIndex];
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

    /**
     * Maatwebsite/PhpSpreadsheet rows may be 0-based or 1-based.
     *
     * @param  array<int, mixed>  $row
     */
    private static function rowIndexOffset(array $row): int
    {
        if ($row === [] || array_key_exists(0, $row)) {
            return 0;
        }

        if (array_key_exists(1, $row)) {
            return 1;
        }

        return 0;
    }

    private static function normalizeColumnLetter(string $column): string
    {
        return strtoupper(preg_replace('/[^A-Za-z]/', '', $column) ?? '');
    }

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
