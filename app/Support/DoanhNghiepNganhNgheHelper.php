<?php

namespace App\Support;

use App\Models\DanhMucNganhNghe;
use Illuminate\Support\Facades\Cache;

class DoanhNghiepNganhNgheHelper
{
    public const CODE_MAX_LENGTH = 20;

    /** @var array<string, true>|null */
    private static ?array $catalogMaSet = null;

    /**
     * @param  mixed  $value
     * @return array<int, string>|null
     */
    public static function normalizeCodes(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = preg_split('/\s*[,;]\s*/', trim($value)) ?: [];
            }
        }

        if (! is_array($value)) {
            return null;
        }

        $codes = [];
        foreach ($value as $item) {
            $extracted = self::extractPrimaryCode($item);
            if ($extracted === null) {
                continue;
            }

            $resolved = self::resolveExistingCatalogMa($extracted);
            if ($resolved !== null) {
                $codes[] = $resolved;
            }
        }

        $codes = array_values(array_unique($codes));

        return $codes === [] ? null : $codes;
    }

    /**
     * Trích mã VSIC số từ ô Excel kiểu "2391: Mô tả ngành nghề".
     */
    public static function extractPrimaryCode(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        // "2391: Mô tả..." hoặc "2391 - Mô tả..."
        if (preg_match('/^(\d{1,20})\s*[:：\-–—]/u', $text, $matches)) {
            return $matches[1];
        }

        // Chỉ nhận mã số VSIC (không nhận text ngắn kiểu "môi giới")
        if (preg_match('/^(\d{1,20})\b/u', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Map mã Excel sang mã có trong danh_muc_nganh_nghes.
     * Ưu tiên khớp exact, sau đó prefix dài nhất (6820 → 682).
     */
    public static function resolveExistingCatalogMa(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        $catalog = self::catalogMaSet();

        if (isset($catalog[$code])) {
            return $code;
        }

        // VSIC thiếu cấp lá: 6820 → 682 (không map 99999 → 99).
        $maxTrim = 2;
        $len = mb_strlen($code);
        for ($trim = 1; $trim <= $maxTrim; $trim++) {
            $prefixLen = $len - $trim;
            if ($prefixLen < 2) {
                break;
            }
            $prefix = mb_substr($code, 0, $prefixLen);
            if (isset($catalog[$prefix])) {
                return $prefix;
            }
        }

        return null;
    }

    /**
     * Chuẩn hóa field ngành nghề trên payload camelCase (import / field-update).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeImportCamelRow(array $data): array
    {
        if (array_key_exists('nganhNgheKDChinh', $data)) {
            $data['nganhNgheKDChinh'] = self::resolveExistingCatalogMa(
                self::extractPrimaryCode($data['nganhNgheKDChinh'])
            );
        }

        if (array_key_exists('nganhNgheKD', $data) && $data['nganhNgheKD'] !== null && $data['nganhNgheKD'] !== '') {
            $codes = self::normalizeCodes($data['nganhNgheKD']);
            $data['nganhNgheKD'] = $codes === null ? null : implode('; ', $codes);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function apply(array $data): array
    {
        if (array_key_exists('nganh_nghe_kd_chinh', $data)) {
            $data['nganh_nghe_kd_chinh'] = self::resolveExistingCatalogMa(
                self::extractPrimaryCode($data['nganh_nghe_kd_chinh'])
            );
        }

        if (array_key_exists('nganh_nghe_kd', $data)) {
            $data['nganh_nghe_kd'] = self::normalizeCodes($data['nganh_nghe_kd']);
        }

        return $data;
    }

    /**
     * @return array<string, true>
     */
    private static function catalogMaSet(): array
    {
        if (self::$catalogMaSet !== null) {
            return self::$catalogMaSet;
        }

        /** @var array<int, string> $codes */
        $codes = Cache::remember('danh_muc_nganh_nghes.ma_set', 300, static function () {
            return DanhMucNganhNghe::query()->pluck('ma')->all();
        });

        self::$catalogMaSet = [];
        foreach ($codes as $ma) {
            $key = trim((string) $ma);
            if ($key !== '') {
                self::$catalogMaSet[$key] = true;
            }
        }

        return self::$catalogMaSet;
    }

    /** Dùng trong test / sau khi sync danh mục. */
    public static function clearCatalogCache(): void
    {
        self::$catalogMaSet = null;
        Cache::forget('danh_muc_nganh_nghes.ma_set');
    }
}
