<?php

namespace App\Support;

class ImportExcelKindGuard
{
    public static function assertExpectedKind(string $absolutePath, string $expectedKind): void
    {
        $detected = ImportExcelKindDetector::detectFromPath($absolutePath);

        if ($detected === null || $detected === $expectedKind) {
            return;
        }

        $message = $expectedKind === ImportExcelKindDetector::KIND_HTX
            ? 'File Excel có vẻ là danh sách doanh nghiệp. Vui lòng import tại trang Quản lý doanh nghiệp, không phải Hợp tác xã.'
            : 'File Excel có vẻ là danh sách hợp tác xã (HTX). Vui lòng import tại trang Hợp tác xã, không phải Quản lý doanh nghiệp.';

        ImportUploadValidator::throwError($message, 'wrong_import_kind');
    }

    /**
     * @param  array<string, mixed>|null  $columnMap
     */
    public static function assertHopTacXaColumnMap(?array $columnMap): void
    {
        if ($columnMap === null || $columnMap === []) {
            return;
        }

        $allowed = array_keys(HopTacXaExcelColumns::CAMEL_TO_SNAKE);
        $companyOnlyKeys = [
            'tenDoanhNghiep',
            'maSoDoanhNghiep',
            'nguoiDaiDienTen',
            'chuSoHuuTen',
            'loaiHinhDN',
            'nganhNgheKDChinh',
        ];

        foreach (array_keys($columnMap) as $key) {
            if (in_array($key, $companyOnlyKeys, true)) {
                ImportUploadValidator::throwError(
                    'Ánh xạ cột đang dùng trường doanh nghiệp. Vui lòng chọn cấu hình import HTX hoặc import tại trang Doanh nghiệp.',
                    'wrong_column_map',
                );
            }

            if (!in_array($key, $allowed, true)) {
                ImportUploadValidator::throwError(
                    "Trường ánh xạ không hợp lệ cho import HTX: {$key}.",
                    'invalid_column_map',
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>|null  $columnMap
     */
    public static function assertDoanhNghiepColumnMap(?array $columnMap): void
    {
        if ($columnMap === null || $columnMap === []) {
            return;
        }

        $htxOnlyKeys = [
            'tenHtx',
            'chuTichHdqtTen',
            'dsThanhVien',
            'diaChiMoi',
            'dienTichHa',
            'hoatDong',
        ];

        foreach (array_keys($columnMap) as $key) {
            if (in_array($key, $htxOnlyKeys, true)) {
                ImportUploadValidator::throwError(
                    'Ánh xạ cột đang dùng trường hợp tác xã. Vui lòng import file này tại trang Hợp tác xã.',
                    'wrong_column_map',
                );
            }
        }
    }
}
