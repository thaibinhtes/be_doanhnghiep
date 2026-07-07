<?php

namespace App\Support;

use App\Models\HanhChinhMapping;
use App\Models\QuanHuyenCu;
use App\Models\TinhThanh;
use App\Models\TinhThanhCu;
use App\Models\XaPhuong;
use App\Models\XaPhuongCu;
use Illuminate\Support\Str;

class HanhChinhCodeGenerator
{
    public static function provinceCode(string $fullName, ?string $explicit = null): string
    {
        if ($explicit !== null && $explicit !== '') {
            return $explicit;
        }

        return 'CU-P-' . substr(md5(mb_strtolower(trim($fullName))), 0, 10);
    }

  public static function districtCode(string $provinceCode, string $fullName, ?string $explicit = null): string
    {
        if ($explicit !== null && $explicit !== '') {
            return $explicit;
        }

        if ($provinceCode === '') {
            return self::districtCodeStandalone($fullName);
        }

        return 'CU-D-' . substr(md5(mb_strtolower($provinceCode . '|' . trim($fullName))), 0, 10);
    }

    /** Mã huyện/quận cũ — không phụ thuộc tỉnh (hệ thống chỉ dùng An Giang). */
    public static function districtCodeStandalone(string $fullName, ?string $explicit = null): string
    {
        if ($explicit !== null && $explicit !== '') {
            return $explicit;
        }

        return 'CU-D-' . substr(md5(mb_strtolower(trim($fullName))), 0, 10);
    }

    public static function wardCode(string $districtCode, string $fullName, ?string $explicit = null): string
    {
        if ($explicit !== null && $explicit !== '') {
            return $explicit;
        }

        return 'CU-W-' . substr(md5(mb_strtolower($districtCode . '|' . trim($fullName))), 0, 10);
    }

    public static function normalizeName(string $name): string
    {
        return preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
    }
}
