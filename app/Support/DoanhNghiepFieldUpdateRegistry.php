<?php

namespace App\Support;

class DoanhNghiepFieldUpdateRegistry
{
    public const LOOKUP_FIELDS = [
        'maSoDoanhNghiep' => 'Mã số doanh nghiệp',
        'tenDoanhNghiep' => 'Tên doanh nghiệp',
        'dienThoai' => 'Điện thoại',
    ];

    public const UPDATE_FIELDS = [
        'tenDoanhNghiep' => 'Tên doanh nghiệp',
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
        'dsCoDong' => 'DS cổ đông',
        'loaiDN' => 'Loại DN',
        'long' => 'Kinh độ (long)',
        'lat' => 'Vĩ độ (lat)',
    ];

    /** @var list<string> */
    public const ADMIN_FIELDS = [
        'quanHuyenCu',
        'quanHuyenMoi',
        'phuongXaCu',
        'phuongXaMoi',
        'diaChiCu',
        'diaChiMoi',
    ];

    /**
     * @return array{lookupFields: array<string, string>, updateFields: array<string, string>}
     */
    public static function options(): array
    {
        return [
            'lookupFields' => self::LOOKUP_FIELDS,
            'updateFields' => self::UPDATE_FIELDS,
        ];
    }

    public static function isLookupField(string $key): bool
    {
        return array_key_exists($key, self::LOOKUP_FIELDS);
    }

    public static function isUpdateField(string $key): bool
    {
        return array_key_exists($key, self::UPDATE_FIELDS);
    }

    public static function isAdminField(string $key): bool
    {
        return in_array($key, self::ADMIN_FIELDS, true);
    }

    public static function lookupDbColumn(string $key): ?string
    {
        return match ($key) {
            'maSoDoanhNghiep' => 'ma_so_doanh_nghiep',
            'tenDoanhNghiep' => 'ten_doanh_nghiep',
            'dienThoai' => 'dien_thoai',
            default => null,
        };
    }
}
