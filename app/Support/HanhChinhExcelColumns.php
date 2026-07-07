<?php

namespace App\Support;

class HanhChinhExcelColumns
{
    /** Mã tỉnh mặc định khi tra cứu đơn vị hành chính mới (An Giang). */
    public const DEFAULT_NEW_PROVINCE_CODE = '91';

    /**
     * Cột import Excel — không lưu tỉnh, chỉ huyện + đơn vị cũ + loại (+ mapping mới nếu có).
     */
    public const COLUMNS = [
        'stt' => 'STT',
        'quanHuyenCu' => 'Huyện/Thị xã/Thành phố cũ',
        'xaPhuongCu' => 'Đơn vị hành chính cũ',
        'loaiCu' => 'Loại (cũ)',
        'xaPhuongMoi' => 'Đơn vị hành chính mới',
        'loaiMoi' => 'Loại (mới)',
    ];

    /** Chỉ import danh mục đơn vị cũ (3 cột). */
    public const LEGACY_ONLY_COLUMNS = [
        'quanHuyenCu' => 'Huyện/Thị xã/Thành phố cũ',
        'xaPhuongCu' => 'Đơn vị hành chính cũ',
        'loaiCu' => 'Loại (cũ)',
    ];

    /** Chỉ import đơn vị hành chính mới (2 cột). */
    public const NEW_ONLY_COLUMNS = [
        'xaPhuongMoi' => 'Đơn vị hành chính mới',
        'loaiMoi' => 'Loại (mới)',
    ];

    public static function columnLabels(): array
    {
        return self::COLUMNS;
    }

    public static function legacyOnlyColumnLabels(): array
    {
        return self::LEGACY_ONLY_COLUMNS;
    }

    public static function newOnlyColumnLabels(): array
    {
        return self::NEW_ONLY_COLUMNS;
    }

    public static function normalizeImportValue(string $key, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        return match ($key) {
            'stt' => is_numeric($value) ? (int) $value : null,
            default => trim((string) $value),
        };
    }
}
