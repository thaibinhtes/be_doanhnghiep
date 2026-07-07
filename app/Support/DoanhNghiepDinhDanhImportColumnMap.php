<?php

namespace App\Support;

class DoanhNghiepDinhDanhImportColumnMap
{
    public const DEFAULT_START_ROW = 2;

    /** @var array<string, list<string>> */
    public const DEFAULT_COLUMN_MAP = [
        'maSoDoanhNghiep' => ['A'],
        'tenDoanhNghiep' => ['B'],
    ];

    /**
     * @param  array<string, mixed>|null  $customMap
     * @return array<string, list<string>>
     */
    public static function resolve(?array $customMap = null): array
    {
        if ($customMap === null || $customMap === []) {
            return self::DEFAULT_COLUMN_MAP;
        }

        $resolved = [];
        foreach ($customMap as $key => $columns) {
            if (!is_string($key) || !is_array($columns)) {
                continue;
            }

            $letters = DoanhNghiepImportColumnMap::expandColumnLetters($columns);
            if ($letters !== []) {
                $resolved[$key] = $letters;
            }
        }

        return $resolved === [] ? self::DEFAULT_COLUMN_MAP : $resolved;
    }

    /**
     * @param  array<string, mixed>  $columnMap
     * @return array<string, list<string>>
     */
    public static function normalizeStoredColumnMap(array $columnMap): array
    {
        return self::resolve($columnMap);
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, list<string>>  $columnMap
     * @return array<string, mixed>
     */
    public static function parseRow(array $row, array $columnMap): array
    {
        return DoanhNghiepImportColumnMap::parseRow($row, $columnMap);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function isEmptyRow(array $data): bool
    {
        $meaningful = array_filter($data, static fn ($value) => $value !== null && $value !== '');

        return $meaningful === [];
    }
}
