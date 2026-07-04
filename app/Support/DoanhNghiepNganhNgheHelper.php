<?php

namespace App\Support;

class DoanhNghiepNganhNgheHelper
{
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

        if (!is_array($value)) {
            return null;
        }

        $codes = array_values(array_unique(array_filter(array_map(
            static fn ($code) => is_string($code) || is_numeric($code) ? trim((string) $code) : '',
            $value
        ))));

        return $codes === [] ? null : $codes;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function apply(array $data): array
    {
        if (array_key_exists('nganh_nghe_kd_chinh', $data)) {
            $code = $data['nganh_nghe_kd_chinh'];
            $data['nganh_nghe_kd_chinh'] = ($code === null || $code === '') ? null : trim((string) $code);
        }

        if (array_key_exists('nganh_nghe_kd', $data)) {
            $data['nganh_nghe_kd'] = self::normalizeCodes($data['nganh_nghe_kd']);
        }

        return $data;
    }
}
