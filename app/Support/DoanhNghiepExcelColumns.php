<?php

namespace App\Support;

use App\Models\DoanhNghiep;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class DoanhNghiepExcelColumns
{
    /** @var list<string> */
    private const DATE_FIELDS = [
        'ngaySinhNguoiDaiDien',
        'ngayCap',
        'ngayDangKyThayDoi',
        'ngayDinhDanh',
    ];

    /**
     * Excel export column definitions: camelCase key => Vietnamese heading.
     */
    public const COLUMNS = [
        'tt' => 'TT',
        'maSoDoanhNghiep' => 'Mã số doanh nghiệp',
        'tenDoanhNghiep' => 'Tên doanh nghiệp',
        'diaChi' => 'Địa chỉ trụ sở chính',
        'quanHuyen' => 'Quận / Huyện',
        'phuongXa' => 'Phường/xã',
        'tinhThanhCu' => 'Cấp tỉnh (cũ)',
        'tinhThanhMoi' => 'Cấp tỉnh (mới)',
        'quanHuyenCu' => 'Quận / Huyện cũ',
        'quanHuyenMoi' => 'Quận / Huyện mới',
        'phuongXaCu' => 'Phường / Xã cũ',
        'phuongXaMoi' => 'Phường / Xã mới',
        'vonDieuLe' => 'Vốn điều lệ',
        'trangThai' => 'Trạng thái',
        'dienThoai' => 'Điện thoại',
        'nguoiDaiDienTen' => 'Người đại diện theo pháp luật',
        'ngaySinhNguoiDaiDien' => 'Ngày sinh người đại diện',
        'chuSoHuuTen' => 'Chủ sở hữu',
        'nganhNgheKDChinh' => 'Ngành nghề KD chính',
        'nganhNgheKD' => 'Ngành nghề KD',
        'ngayCap' => 'Ngày cấp',
        'ngayDangKyThayDoi' => 'Ngày đăng ký thay đổi',
        'loaiHinhDN' => 'Loại hình DN',
        'soLuongLaoDong' => 'Số lượng lao động',
        'dsThanhVienGopVon' => 'DS thành viên góp vốn',
        'dsCoDong' => 'DS cổ đông',
        'daCapNhatDinhDanh' => 'Định danh',
        'loaiDN' => 'Loại DN',
        'long' => 'Kinh độ (long)',
        'lat' => 'Vĩ độ (lat)',
    ];

    /**
     * Import mapping fields (không gồm Quận/Huyện + Phường/xã tổng).
     * Địa bàn gồm cấp tỉnh cũ, quận/huyện cũ-mới, phường/xã cũ-mới và địa chỉ cũ-mới.
     *
     * @var array<string, string>
     */
    public const IMPORT_COLUMNS = [
        'tt' => 'TT',
        'maSoDoanhNghiep' => 'Mã số doanh nghiệp',
        'tenDoanhNghiep' => 'Tên doanh nghiệp',
        'tinhThanhCu' => 'Cấp tỉnh (cũ)',
        'tinhThanhMoi' => 'Cấp tỉnh (mới)',
        'quanHuyenCu' => 'Quận / Huyện cũ',
        'quanHuyenMoi' => 'Quận / Huyện mới',
        'phuongXaCu' => 'Phường / Xã cũ',
        'phuongXaMoi' => 'Phường / Xã mới',
        'diaChiCu' => 'Địa chỉ cũ',
        'diaChiMoi' => 'Địa chỉ mới',
        'vonDieuLe' => 'Vốn điều lệ',
        'trangThai' => 'Trạng thái',
        'dienThoai' => 'Điện thoại',
        'nguoiDaiDienTen' => 'Người đại diện theo pháp luật',
        'ngaySinhNguoiDaiDien' => 'Ngày sinh người đại diện',
        'chuSoHuuTen' => 'Chủ sở hữu',
        'nganhNgheKDChinh' => 'Ngành nghề KD chính',
        'nganhNgheKD' => 'Ngành nghề KD',
        'ngayCap' => 'Ngày cấp',
        'ngayDangKyThayDoi' => 'Ngày đăng ký thay đổi',
        'loaiHinhDN' => 'Loại hình DN',
        'soLuongLaoDong' => 'Số lượng lao động',
        'dsThanhVienGopVon' => 'DS thành viên góp vốn',
        'dsCoDong' => 'DS cổ đông',
        'daCapNhatDinhDanh' => 'Định danh',
        'loaiDN' => 'Loại DN',
        'long' => 'Kinh độ (long)',
        'lat' => 'Vĩ độ (lat)',
    ];

    /**
     * Map camelCase keys to snake_case database columns.
     * Địa bàn hành chính lưu text trước; mã liên kết đồng bộ ở bước sau.
     */
    public const CAMEL_TO_SNAKE = [
        'tt' => 'tt',
        'maSoDoanhNghiep' => 'ma_so_doanh_nghiep',
        'tenDoanhNghiep' => 'ten_doanh_nghiep',
        'diaChi' => 'dia_chi',
        'diaChiCu' => 'dia_chi_cu',
        'diaChiMoi' => 'dia_chi_moi',
        'long' => 'long',
        'lat' => 'lat',
        'quanHuyen' => 'quan_huyen',
        'phuongXa' => 'phuong_xa',
        'tinhThanhCu' => 'tinh_thanh_cu',
        'tinhThanhMoi' => 'tinh_thanh_moi',
        'quanHuyenCu' => 'quan_huyen_cu',
        'quanHuyenMoi' => 'quan_huyen_moi',
        'phuongXaCu' => 'xa_phuong_cu',
        'phuongXaMoi' => 'xa_phuong_moi',
        'vonDieuLe' => 'von_dieu_le',
        'trangThai' => 'trang_thai',
        'daCapNhatDinhDanh' => 'da_cap_nhat_dinh_danh',
        'dienThoai' => 'dien_thoai',
        'nguoiDaiDienTen' => 'nguoi_dai_dien_ten',
        'ngaySinhNguoiDaiDien' => 'ngay_sinh_nguoi_dai_dien',
        'chuSoHuuTen' => 'chu_so_huu_ten',
        'nganhNgheKDChinh' => 'nganh_nghe_kd_chinh',
        'nganhNgheKD' => 'nganh_nghe_kd',
        'ngayCap' => 'ngay_cap',
        'ngayDangKyThayDoi' => 'ngay_dang_ky_thay_doi',
        'loaiHinhDN' => 'loai_hinh_dn',
        'soLuongLaoDong' => 'so_luong_lao_dong',
        'loaiDN' => 'loai_dn',
        'dsCoDong' => 'ds_co_dong',
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
     * Labels for import / mapping-config UI.
     *
     * @return array<string, string>
     */
    public static function importColumnLabels(): array
    {
        return self::IMPORT_COLUMNS;
    }

    /**
     * Build one export row — values strictly follow COLUMNS key order.
     *
     * @return list<mixed>
     */
    public static function exportValues(DoanhNghiep $model, int $sequence): array
    {
        $row = [
            'tt' => $sequence,
            'maSoDoanhNghiep' => $model->ma_so_doanh_nghiep !== null ? (string) $model->ma_so_doanh_nghiep : '',
            'tenDoanhNghiep' => $model->ten_doanh_nghiep ?? '',
            'diaChi' => $model->dia_chi ?? '',
            'quanHuyen' => $model->quan_huyen ?? '',
            'phuongXa' => $model->phuong_xa ?? '',
            'tinhThanhCu' => $model->tinh_thanh_cu
                ?? ($model->relationLoaded('tinhThanhCu') ? ($model->tinhThanhCu?->full_name ?? '') : ''),
            'tinhThanhMoi' => $model->tinh_thanh_moi ?? '',
            'quanHuyenCu' => $model->quan_huyen_cu
                ?? ($model->relationLoaded('quanHuyenCu') ? ($model->quanHuyenCu?->full_name ?? '') : ''),
            'quanHuyenMoi' => $model->quan_huyen_moi
                ?? ($model->relationLoaded('tinhThanh') ? ($model->tinhThanh?->full_name ?? '') : ''),
            'phuongXaCu' => $model->xa_phuong_cu
                ?? ($model->relationLoaded('xaPhuongCu') ? ($model->xaPhuongCu?->full_name ?? '') : ''),
            'phuongXaMoi' => $model->xa_phuong_moi
                ?? ($model->relationLoaded('xaPhuong') ? ($model->xaPhuong?->full_name ?? '') : ''),
            'vonDieuLe' => $model->von_dieu_le !== null ? (string) $model->von_dieu_le : '',
            'trangThai' => $model->trang_thai ?? '',
            'dienThoai' => $model->dien_thoai !== null ? (string) $model->dien_thoai : '',
            'nguoiDaiDienTen' => $model->nguoi_dai_dien_ten ?? '',
            'ngaySinhNguoiDaiDien' => $model->ngay_sinh_nguoi_dai_dien ?? '',
            'chuSoHuuTen' => $model->chu_so_huu_ten ?? '',
            'nganhNgheKDChinh' => $model->nganh_nghe_kd_chinh ?? '',
            'nganhNgheKD' => is_array($model->nganh_nghe_kd)
                ? implode('; ', $model->nganh_nghe_kd)
                : '',
            'ngayCap' => $model->ngay_cap ?? '',
            'ngayDangKyThayDoi' => $model->ngay_dang_ky_thay_doi ?? '',
            'loaiHinhDN' => $model->loai_hinh_dn ?? '',
            'soLuongLaoDong' => $model->so_luong_lao_dong ?? '',
            'dsThanhVienGopVon' => self::formatMembersForExport($model->memberCompanies),
            'dsCoDong' => $model->ds_co_dong ?? '',
            'daCapNhatDinhDanh' => $model->da_cap_nhat_dinh_danh ? 'Đã đăng ký định danh' : 'Chưa đăng ký định danh',
            'loaiDN' => $model->loai_dn ?? '',
            'long' => $model->long ?? '',
            'lat' => $model->lat ?? '',
        ];

        return array_map(
            static fn (string $key) => $row[$key] ?? '',
            self::keys()
        );
    }

    /**
     * Excel column letter(s) for a camelCase key (1-based column index).
     */
    public static function columnLetterForKey(string $key): ?string
    {
        $index = array_search($key, self::keys(), true);
        if ($index === false) {
            return null;
        }

        return self::columnIndexToLetter($index + 1);
    }

    private static function columnIndexToLetter(int $columnIndex): string
    {
        $letter = '';
        while ($columnIndex > 0) {
            $columnIndex--;
            $letter = chr(65 + ($columnIndex % 26)).$letter;
            $columnIndex = intdiv($columnIndex, 26);
        }

        return $letter;
    }

    /**
     * Convert a row keyed by Vietnamese headings to camelCase.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function rowFromHeadings(array $row): array
    {
        $headingToKey = array_flip(self::COLUMNS);
        $result = [];

        foreach ($row as $heading => $value) {
            $normalizedHeading = trim((string) $heading);
            $key = $headingToKey[$normalizedHeading] ?? null;

            if ($key === null) {
                continue;
            }

            $result[$key] = self::normalizeValue($key, $value);
        }

        return $result;
    }

    /**
     * Convert camelCase row to snake_case for database storage.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mapToSnake(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (! isset(self::CAMEL_TO_SNAKE[$key])) {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $result[self::CAMEL_TO_SNAKE[$key]] = $value;
        }

        return $result;
    }

    /**
     * Format capital members for Excel export.
     */
    public static function formatMembersForExport($memberCompanies): string
    {
        if (! $memberCompanies || $memberCompanies->isEmpty()) {
            return '';
        }

        return $memberCompanies
            ->map(function ($mc) {
                $parts = array_filter([
                    $mc->member?->full_name ?? '',
                    $mc->position ?? null,
                    $mc->investment_amount !== null ? (string) $mc->investment_amount : null,
                ]);

                return implode(' | ', $parts);
            })
            ->implode('; ');
    }

    /**
     * Parse capital members text from Excel import.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function parseMembersFromImport(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        $members = [];

        foreach (explode(';', $text) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $segment));
            $fullName = $parts[0] ?? '';

            if ($fullName === '') {
                continue;
            }

            $members[] = [
                'fullName' => $fullName,
                'position' => $parts[1] ?? null,
                'investmentAmount' => isset($parts[2]) && is_numeric($parts[2]) ? (float) $parts[2] : null,
                'dateJoin' => null,
                'memberId' => null,
            ];
        }

        return $members;
    }

    /**
     * Normalize a single import field value by camelCase key.
     */
    public static function normalizeImportValue(string $key, mixed $value): mixed
    {
        return self::normalizeValue($key, $value);
    }

    private static function normalizeValue(string $key, mixed $value): mixed
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

        if (in_array($key, self::DATE_FIELDS, true)) {
            return self::normalizeDate($value);
        }

        return match ($key) {
            'tt', 'soLuongLaoDong' => is_numeric($value) ? (int) $value : null,
            'long', 'lat' => is_numeric($value) ? (float) $value : null,
            'daCapNhatDinhDanh' => self::parseBoolean($value),
            'maSoDoanhNghiep', 'vonDieuLe', 'dienThoai' => self::asString($value),
            'nganhNgheKD' => is_string($value) ? $value : self::asString($value),
            default => is_string($value) ? $value : self::asString($value),
        };
    }

    private static function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $serial = (float) $value;

            if ($serial >= 1000 && $serial < 1_000_000) {
                try {
                    return ExcelDate::excelToDateTimeObject($serial)->format('d/m/Y');
                } catch (\Throwable) {
                    // fall through to string coercion
                }
            }
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    /**
     * Excel often reads numeric-looking cells as int/float — coerce to string for validation/DB.
     */
    private static function asString(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (floor($value) === $value) {
                return (string) (int) $value;
            }

            return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }

        return trim((string) $value);
    }

    private static function parseBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = mb_strtolower(trim((string) $value));

        if (in_array($normalized, ['1', 'true', 'có', 'co', 'đã đăng ký định danh', 'da dang ky dinh danh', 'định danh', 'dinh danh', 'x'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'không', 'khong', 'chưa đăng ký định danh', 'chua dang ky dinh danh', 'chưa định danh', 'chua dinh danh', ''], true)) {
            return false;
        }

        return null;
    }
}
