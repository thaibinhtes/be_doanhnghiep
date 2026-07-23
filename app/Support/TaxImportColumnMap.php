<?php

namespace App\Support;

class TaxImportColumnMap
{
    public const DEFAULT_START_ROW = 4;

    public const TAX_UNIT_END_COLUMN = 'C';
    public const COMPANY_TAX_END_COLUMN = 'D';
    public const COOPERATIVE_TAX_END_COLUMN = 'D';

    /** @var array<string, list<string>> */
    public const TAX_UNIT_COLUMN_MAP = [
        'unitCode' => ['B'],
        'unitName' => ['C'],
    ];

    /** @var array<string, list<string>> */
    public const COMPANY_TAX_COLUMN_MAP = [
        'taxUnitCode' => ['B'],
        'taxCode' => ['D'],
    ];

    /** @var array<string, list<string>> */
    public const COOPERATIVE_TAX_COLUMN_MAP = [
        'taxUnitCode' => ['B'],
        'taxCode' => ['D'],
    ];

    /**
     * @param  array<string, mixed>|null  $customMap
     * @return array<string, list<string>>
     */
    public static function resolveTaxUnit(?array $customMap = null): array
    {
        if ($customMap === null || $customMap === []) {
            return self::TAX_UNIT_COLUMN_MAP;
        }

        return self::normalizeStoredColumnMap($customMap);
    }

    /**
     * @param  array<string, mixed>|null  $customMap
     * @return array<string, list<string>>
     */
    public static function resolveCompanyTax(?array $customMap = null): array
    {
        if ($customMap === null || $customMap === []) {
            return self::COMPANY_TAX_COLUMN_MAP;
        }

        return self::normalizeStoredColumnMap($customMap);
    }

    /**
     * @param  array<string, mixed>|null  $customMap
     * @return array<string, list<string>>
     */
    public static function resolveCooperativeTax(?array $customMap = null): array
    {
        if ($customMap === null || $customMap === []) {
            return self::COOPERATIVE_TAX_COLUMN_MAP;
        }

        return self::normalizeStoredColumnMap($customMap);
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
            $raw = self::readColumns($row, $columns);
            if ($raw === null) {
                continue;
            }

            $value = trim((string) $raw);
            if ($value === '') {
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function isEmptyTaxUnitRow(array $data): bool
    {
        return ($data['unitCode'] ?? '') === '' && ($data['unitName'] ?? '') === '';
    }

    /**
     * Đọc 1 dòng worksheet theo chữ cột ánh xạ (dùng cho preview).
     *
     * @param  array<string, list<string>>  $columnMap
     * @return array<string, mixed>
     */
    public static function parseWorksheetRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet, int $rowIndex, array $columnMap): array
    {
        $result = [];

        foreach ($columnMap as $key => $columns) {
            if ($columns === []) {
                continue;
            }

            $rawValue = null;
            $ordered = $columns;
            usort(
                $ordered,
                static fn (string $left, string $right): int => self::columnLetterToIndex($left) <=> self::columnLetterToIndex($right),
            );

            foreach ($ordered as $letter) {
                $text = ExcelLetterCellReader::read($worksheet, $rowIndex, $letter);
                if ($text !== null && $text !== '') {
                    $rawValue = $text;
                    break;
                }
            }

            if ($rawValue === null || $rawValue === '') {
                continue;
            }

            $result[$key] = trim((string) $rawValue);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function isEmptyCompanyTaxRow(array $data): bool
    {
        return ($data['taxCode'] ?? '') === '' && ($data['taxUnitCode'] ?? '') === '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function isEmptyCooperativeTaxRow(array $data): bool
    {
        return ($data['taxCode'] ?? '') === '' && ($data['taxUnitCode'] ?? '') === '';
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

            return trim((string) $value);
        }

        return null;
    }

    /**
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

    /**
     * @param  array<string, list<string>>  $columnMap
     */
    public static function resolveEndColumn(array $columnMap, string $fallback = 'B'): string
    {
        $maxIndex = -1;
        foreach ($columnMap as $columns) {
            foreach ($columns as $column) {
                $index = self::columnLetterToIndex((string) $column);
                if ($index > $maxIndex) {
                    $maxIndex = $index;
                }
            }
        }

        if ($maxIndex < 0) {
            return $fallback;
        }

        return self::columnIndexToLetter($maxIndex);
    }
}
