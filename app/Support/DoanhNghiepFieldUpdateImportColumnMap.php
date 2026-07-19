<?php

namespace App\Support;

use InvalidArgumentException;

class DoanhNghiepFieldUpdateImportColumnMap
{
    public const DEFAULT_START_ROW = 2;

    public const DEFAULT_LOOKUP_FIELD = 'maSoDoanhNghiep';

    /** @var array<string, list<string>> */
    public const DEFAULT_COLUMN_MAP = [
        'maSoDoanhNghiep' => ['A'],
        'phuongXaCu' => ['B'],
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

            if (
                !DoanhNghiepFieldUpdateRegistry::isLookupField($key)
                && !DoanhNghiepFieldUpdateRegistry::isUpdateField($key)
            ) {
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
     * @param  array<string, list<string>>  $columnMap
     */
    public static function assertValid(array $columnMap, string $lookupField): void
    {
        if (!DoanhNghiepFieldUpdateRegistry::isLookupField($lookupField)) {
            throw new InvalidArgumentException("Trường đối chiếu không hợp lệ: {$lookupField}.");
        }

        if (!isset($columnMap[$lookupField]) || $columnMap[$lookupField] === []) {
            throw new InvalidArgumentException('Cần ánh xạ cột cho trường đối chiếu.');
        }

        $updateKeys = [];
        foreach (array_keys($columnMap) as $key) {
            if (!is_string($key)) {
                continue;
            }
            if (DoanhNghiepFieldUpdateRegistry::isUpdateField($key) && $key !== $lookupField) {
                $updateKeys[] = $key;
            }
        }

        if ($updateKeys === []) {
            throw new InvalidArgumentException('Cần ánh xạ ít nhất một field cần cập nhật.');
        }

        $usedColumns = [];
        foreach ($columnMap as $key => $letters) {
            foreach ($letters as $letter) {
                $normalized = strtoupper((string) $letter);
                if (isset($usedColumns[$normalized]) && $usedColumns[$normalized] !== $key) {
                    throw new InvalidArgumentException(
                        "Cột Excel {$normalized} đang được dùng cho nhiều field.",
                    );
                }
                $usedColumns[$normalized] = $key;
            }
        }
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

    /**
     * @param  array<string, list<string>>  $columnMap
     */
    public static function resolveEndColumn(array $columnMap, string $fallback = 'B'): string
    {
        $maxIndex = -1;

        foreach ($columnMap as $letters) {
            foreach ($letters as $letter) {
                $normalized = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $letter) ?? '');
                if ($normalized === '') {
                    continue;
                }

                $index = 0;
                for ($i = 0, $len = strlen($normalized); $i < $len; $i++) {
                    $index = ($index * 26) + (ord($normalized[$i]) - ord('A') + 1);
                }
                $index -= 1;

                if ($index > $maxIndex) {
                    $maxIndex = $index;
                }
            }
        }

        if ($maxIndex < 0) {
            return $fallback;
        }

        $columnIndex = $maxIndex + 1;
        $result = '';
        while ($columnIndex > 0) {
            $columnIndex--;
            $result = chr(65 + ($columnIndex % 26)).$result;
            $columnIndex = intdiv($columnIndex, 26);
        }

        return $result;
    }
}
