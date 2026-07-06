<?php

namespace App\Support;

class HopTacXaExcelColumns
{
    /**
     * Excel column definitions: camelCase key => Vietnamese heading.
     */
    public const COLUMNS = [
        'tt' => 'STT',
        'tenHtx' => 'Tên HTX',
        'maSoThue' => 'Mã số Thuế',
        'namThanhLap' => 'Năm thành lập',
        'chuTichHdqtTen' => 'Họ tên CT HĐQT',
        'dienThoai' => 'Điện thoại',
        'diaChi' => 'Địa chỉ',
        'phuongXa' => 'Xã, Phường',
        'dienTichHa' => 'Diện tích (ha)',
        'vonDieuLe' => 'Vốn điều lệ',
        'soThanhVien' => 'Thành viên',
        'soNguoiLaoDong' => 'Người lao động',
        'linhVuc' => 'Lĩnh vực',
        'hoatDong' => 'Hoạt động',
        'dsThanhVien' => 'Thành viên (chi tiết)',
        'diaChiMoi' => 'Địa chỉ mới',
        'ghiChu' => 'Ghi chú',
    ];

    public const CAMEL_TO_SNAKE = [
        'tt' => 'tt',
        'tenHtx' => 'ten_htx',
        'maSoThue' => 'ma_so_thue',
        'namThanhLap' => 'nam_thanh_lap',
        'chuTichHdqtTen' => 'chu_tich_hdqt_ten',
        'dienThoai' => 'dien_thoai',
        'diaChi' => 'dia_chi',
        'phuongXa' => 'phuong_xa',
        'dienTichHa' => 'dien_tich_ha',
        'vonDieuLe' => 'von_dieu_le',
        'soThanhVien' => 'so_thanh_vien',
        'soNguoiLaoDong' => 'so_nguoi_lao_dong',
        'linhVuc' => 'linh_vuc',
        'hoatDong' => 'hoat_dong',
        'dsThanhVien' => 'ds_thanh_vien',
        'diaChiMoi' => 'dia_chi_moi',
        'ghiChu' => 'ghi_chu',
    ];

    public static function headings(): array
    {
        return array_values(self::COLUMNS);
    }

    public static function keys(): array
    {
        return array_keys(self::COLUMNS);
    }

    /**
     * @return array<string, string>
     */
    public static function columnLabels(): array
    {
        return self::COLUMNS;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mapToSnake(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (!isset(self::CAMEL_TO_SNAKE[$key])) {
                continue;
            }

            $result[self::CAMEL_TO_SNAKE[$key]] = $value;
        }

        return $result;
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
            'tt', 'soThanhVien', 'soNguoiLaoDong' => is_numeric($value) ? (int) $value : null,
            'dienTichHa' => is_numeric($value) ? (float) $value : null,
            'maSoThue', 'vonDieuLe', 'dienThoai', 'namThanhLap' => self::asString($value),
            default => is_string($value) ? $value : self::asString($value),
        };
    }

    /**
     * @return list<mixed>
     */
    public static function exportValues(\App\Models\HopTacXa $model, int $sequence): array
    {
        $row = [
            'tt' => $sequence,
            'tenHtx' => $model->ten_htx ?? '',
            'maSoThue' => $model->ma_so_thue !== null ? (string) $model->ma_so_thue : '',
            'namThanhLap' => $model->nam_thanh_lap ?? '',
            'chuTichHdqtTen' => $model->chu_tich_hdqt_ten ?? '',
            'dienThoai' => $model->dien_thoai !== null ? (string) $model->dien_thoai : '',
            'diaChi' => $model->dia_chi ?? '',
            'phuongXa' => $model->phuong_xa ?? '',
            'dienTichHa' => $model->dien_tich_ha ?? '',
            'vonDieuLe' => $model->von_dieu_le !== null ? (string) $model->von_dieu_le : '',
            'soThanhVien' => $model->so_thanh_vien ?? '',
            'soNguoiLaoDong' => $model->so_nguoi_lao_dong ?? '',
            'linhVuc' => $model->linh_vuc ?? '',
            'hoatDong' => $model->hoat_dong ?? '',
            'dsThanhVien' => $model->ds_thanh_vien ?? '',
            'diaChiMoi' => $model->dia_chi_moi ?? '',
            'ghiChu' => $model->ghi_chu ?? '',
        ];

        return array_map(fn (string $key) => $row[$key] ?? '', self::keys());
    }

    public static function columnLetterForKey(string $key): ?string
    {
        $index = array_search($key, self::keys(), true);

        if ($index === false) {
            return null;
        }

        return self::columnIndexToLetter((int) $index);
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

    private static function asString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim((string) $value);
    }
}
