<?php

namespace App\Support;

use InvalidArgumentException;

class DoanhNghiepDinhDanhImportColumnMap
{
    public const DEFAULT_START_ROW = 2;

    public const DEFAULT_LOOKUP_FIELD = 'maSoDoanhNghiep';

    public const DATE_FIELD = 'ngayDinhDanh';

    /** @var array<string, string> camelCase => label */
    public const LOOKUP_FIELDS = [
        'maSoDoanhNghiep' => 'Mã số doanh nghiệp',
        'tenDoanhNghiep' => 'Tên doanh nghiệp',
    ];

    /** @var array<string, string> camelCase => DB column */
    public const LOOKUP_DB_COLUMNS = [
        'maSoDoanhNghiep' => 'ma_so_doanh_nghiep',
        'tenDoanhNghiep' => 'ten_doanh_nghiep',
    ];

    /** @var array<string, list<string>> */
    public const DEFAULT_COLUMN_MAP = [
        'maSoDoanhNghiep' => ['A'],
        'ngayDinhDanh' => ['B'],
    ];

    /** @var array<string, string> */
    public const COLUMN_LABELS = [
        'maSoDoanhNghiep' => 'Mã số doanh nghiệp',
        'tenDoanhNghiep' => 'Tên doanh nghiệp',
        'ngayDinhDanh' => 'Thời gian định danh',
    ];

    public static function isLookupField(string $field): bool
    {
        return isset(self::LOOKUP_FIELDS[$field]);
    }

    public static function lookupDbColumn(string $field): ?string
    {
        return self::LOOKUP_DB_COLUMNS[$field] ?? null;
    }

    /**
     * @param  array<string, mixed>|null  $customMap
     * @return array<string, list<string>>
     */
    public static function resolve(?array $customMap = null, ?string $lookupField = null): array
    {
        $lookupField = $lookupField && self::isLookupField($lookupField)
            ? $lookupField
            : self::DEFAULT_LOOKUP_FIELD;

        if ($customMap === null || $customMap === []) {
            return [
                $lookupField => self::DEFAULT_COLUMN_MAP['maSoDoanhNghiep'] ?? ['A'],
                self::DATE_FIELD => self::DEFAULT_COLUMN_MAP[self::DATE_FIELD] ?? ['B'],
            ];
        }

        $resolved = [];
        foreach ($customMap as $key => $columns) {
            if (! is_string($key) || ! is_array($columns)) {
                continue;
            }

            // Chỉ giữ field đối chiếu + cột ngày — không cho map field DN khác.
            if ($key !== $lookupField && $key !== self::DATE_FIELD) {
                continue;
            }

            $letters = DoanhNghiepImportColumnMap::expandColumnLetters($columns);
            if ($letters !== []) {
                $resolved[$key] = $letters;
            }
        }

        if (! isset($resolved[$lookupField])) {
            $resolved[$lookupField] = ['A'];
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $columnMap
     * @return array<string, list<string>>
     */
    public static function normalizeStoredColumnMap(array $columnMap, ?string $lookupField = null): array
    {
        return self::resolve($columnMap, $lookupField);
    }

    /**
     * @param  array<string, list<string>>  $columnMap
     */
    public static function assertValid(array $columnMap, string $lookupField): void
    {
        if (! self::isLookupField($lookupField)) {
            throw new InvalidArgumentException("Trường đối chiếu không hợp lệ: {$lookupField}.");
        }

        if (! isset($columnMap[$lookupField]) || $columnMap[$lookupField] === []) {
            throw new InvalidArgumentException('Cần ánh xạ cột Excel cho trường đối chiếu.');
        }
    }

    /**
     * @param  array<string, list<string>>  $columnMap
     * @return array<string, mixed>
     */
    public static function parseExcelRow(\Maatwebsite\Excel\Row $row, array $columnMap): array
    {
        return DoanhNghiepImportColumnMap::parseExcelRow($row, $columnMap);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function isEmptyRow(array $data, string $lookupField): bool
    {
        $value = $data[$lookupField] ?? null;
        if ($value !== null && trim((string) $value) !== '') {
            return false;
        }

        $meaningful = array_filter($data, static fn ($v) => $v !== null && $v !== '');

        return $meaningful === [];
    }

    /**
     * Ô trống / không parse được → thời gian hiện tại.
     */
    public static function resolveIdentityDate(mixed $rawValue, ?string $fallbackDate = null): \Carbon\Carbon
    {
        $parsed = self::parseIdentityDate($rawValue);
        if ($parsed !== null) {
            return $parsed;
        }

        if ($fallbackDate !== null && trim($fallbackDate) !== '') {
            $fallback = self::parseIdentityDate($fallbackDate);
            if ($fallback !== null) {
                return $fallback;
            }
        }

        return now();
    }

    public static function parseIdentityDate(mixed $value): ?\Carbon\Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \Carbon\Carbon) {
            return $value->copy();
        }

        if ($value instanceof \DateTimeInterface) {
            return \Carbon\Carbon::instance($value);
        }

        if (is_numeric($value)) {
            $serial = (float) $value;
            if ($serial >= 1000 && $serial < 1_000_000) {
                try {
                    return \Carbon\Carbon::instance(
                        \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($serial)
                    );
                } catch (\Throwable) {
                    // fall through
                }
            }
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'd/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $format) {
            try {
                $dt = \Carbon\Carbon::createFromFormat($format, $text);
                if ($dt !== false) {
                    return $dt;
                }
            } catch (\Throwable) {
                // try next
            }
        }

        try {
            return \Carbon\Carbon::parse($text);
        } catch (\Throwable) {
            return null;
        }
    }
}
