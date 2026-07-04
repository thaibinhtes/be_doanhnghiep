<?php

namespace App\Support;

use App\Models\DnLoaiHinh;
use App\Models\DoanhNghiep;
use Illuminate\Validation\ValidationException;

class DoanhNghiepLoaiHinhHelper
{
    public static function defaultTypeId(): ?int
    {
        $type = DnLoaiHinh::query()
            ->where('mac_dinh', true)
            ->where('is_active', true)
            ->first();

        return $type?->id;
    }

    /**
     * @param  array<string, mixed>  $data  snake_case keys
     * @return array<string, mixed>
     */
    public static function applyLoaiHinh(array $data, ?DoanhNghiep $existing = null): array
    {
        if (array_key_exists('dn_loai_hinh_id', $data) && $data['dn_loai_hinh_id']) {
            $type = DnLoaiHinh::query()->find($data['dn_loai_hinh_id']);
            if (!$type || !$type->is_active) {
                throw ValidationException::withMessages([
                    'dnLoaiHinhId' => ['Loại hình doanh nghiệp không hợp lệ.'],
                ]);
            }

            $data['loai_hinh_dn'] = $type->ten;

            return $data;
        }

        if (!empty($data['loai_hinh_dn'])) {
            $type = self::resolveByName((string) $data['loai_hinh_dn']);
            if ($type) {
                $data['dn_loai_hinh_id'] = $type->id;
                $data['loai_hinh_dn'] = $type->ten;
            }

            return $data;
        }

        if ($existing?->dn_loai_hinh_id) {
            unset($data['dn_loai_hinh_id']);

            return $data;
        }

        $defaultId = self::defaultTypeId();
        if ($defaultId) {
            $type = DnLoaiHinh::query()->find($defaultId);
            $data['dn_loai_hinh_id'] = $defaultId;
            $data['loai_hinh_dn'] = $type?->ten;
        }

        return $data;
    }

    public static function resolveByName(string $name): ?DnLoaiHinh
    {
        $normalized = mb_strtolower(trim($name));
        if ($normalized === '') {
            return null;
        }

        return DnLoaiHinh::query()
            ->whereRaw('LOWER(ten) = ?', [$normalized])
            ->where('is_active', true)
            ->first();
    }
}
