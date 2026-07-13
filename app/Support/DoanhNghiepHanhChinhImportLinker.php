<?php

namespace App\Support;

use App\Models\QuanHuyenCu;
use App\Models\TinhThanh;
use App\Models\XaPhuong;
use App\Models\XaPhuongCu;

/**
 * Liên kết text địa bàn import với danh mục hành chính.
 * Match được → ghi code/id. Không match → ghi chú thủ công.
 */
class DoanhNghiepHanhChinhImportLinker
{
    /**
     * @param  array<string, mixed>  $data  camelCase import row
     * @return array{
     *     snake: array<string, mixed>,
     *     notes: list<string>
     * }
     */
    public function resolve(array $data): array
    {
        $notes = [];
        $snake = [];

        $quanCu = HanhChinhCodeGenerator::normalizeName((string) ($data['quanHuyenCu'] ?? ''));
        $quanMoi = HanhChinhCodeGenerator::normalizeName((string) ($data['quanHuyenMoi'] ?? ''));
        $xaCu = HanhChinhCodeGenerator::normalizeName((string) ($data['phuongXaCu'] ?? ''));
        $xaMoi = HanhChinhCodeGenerator::normalizeName((string) ($data['phuongXaMoi'] ?? ''));
        $diaChiCu = trim((string) ($data['diaChiCu'] ?? ''));
        $diaChiMoi = trim((string) ($data['diaChiMoi'] ?? ''));

        if ($diaChiCu !== '') {
            $snake['dia_chi_cu'] = $diaChiCu;
        }
        if ($diaChiMoi !== '') {
            $snake['dia_chi_moi'] = $diaChiMoi;
        }

        // Prefer new address for legacy dia_chi display field.
        if ($diaChiMoi !== '') {
            $snake['dia_chi'] = $diaChiMoi;
        } elseif ($diaChiCu !== '') {
            $snake['dia_chi'] = $diaChiCu;
        }

        $legacyDistrict = null;
        if ($quanCu !== '') {
            $legacyDistrict = $this->findLegacyDistrict($quanCu);
            if ($legacyDistrict) {
                $snake['quan_huyen_cu_code'] = $legacyDistrict->code;
            } else {
                $notes[] = "Quận/Huyện cũ chưa khớp danh mục: {$quanCu}";
            }
        }

        if ($quanMoi !== '') {
            $newProvince = $this->findNewProvince($quanMoi);
            if ($newProvince) {
                $snake['tinh_thanh_code'] = $newProvince->code;
                $snake['quan_huyen'] = $newProvince->full_name;
            } else {
                $snake['quan_huyen'] = $quanMoi;
                $notes[] = "Quận/Huyện mới chưa khớp danh mục: {$quanMoi}";
            }
        } elseif ($legacyDistrict) {
            $snake['quan_huyen'] = $legacyDistrict->full_name;
        } elseif ($quanCu !== '') {
            $snake['quan_huyen'] = $quanCu;
        }

        if ($xaCu !== '') {
            $legacyWard = $this->findLegacyWard($xaCu, $legacyDistrict?->code, $quanCu);
            if ($legacyWard) {
                $snake['xa_phuong_cu_code'] = $legacyWard->code;
                if (empty($snake['quan_huyen_cu_code']) && $legacyWard->quan_huyen_cu_code) {
                    $snake['quan_huyen_cu_code'] = $legacyWard->quan_huyen_cu_code;
                }
            } else {
                $notes[] = "Phường/Xã cũ chưa khớp danh mục: {$xaCu}";
            }
        }

        if ($xaMoi !== '') {
            $provinceCode = $snake['tinh_thanh_code'] ?? null;
            $newWard = $this->findNewWard($xaMoi, is_string($provinceCode) ? $provinceCode : null);
            if ($newWard) {
                $snake['xa_phuong_code'] = $newWard->code;
                $snake['phuong_xa'] = $newWard->full_name;
                if (empty($snake['tinh_thanh_code']) && $newWard->tinh_thanh_code) {
                    $snake['tinh_thanh_code'] = $newWard->tinh_thanh_code;
                }
            } else {
                $snake['phuong_xa'] = $xaMoi;
                $notes[] = "Phường/Xã mới chưa khớp danh mục: {$xaMoi}";
            }
        } elseif ($xaCu !== '') {
            $snake['phuong_xa'] = $xaCu;
        }

        if ($notes !== []) {
            $snake['ghi_chu_hanh_chinh'] = implode(' | ', $notes);
        }

        return [
            'snake' => $snake,
            'notes' => $notes,
        ];
    }

    private function findLegacyDistrict(string $name): ?QuanHuyenCu
    {
        $exact = QuanHuyenCu::query()->where('full_name', $name)->get();
        if ($exact->count() === 1) {
            return $exact->first();
        }
        if ($exact->count() > 1) {
            return null;
        }

        $lower = QuanHuyenCu::query()
            ->whereRaw('LOWER(full_name) = ?', [mb_strtolower($name)])
            ->get();

        return $lower->count() === 1 ? $lower->first() : null;
    }

    private function findNewProvince(string $name): ?TinhThanh
    {
        $exact = TinhThanh::query()->where('full_name', $name)->get();
        if ($exact->count() === 1) {
            return $exact->first();
        }
        if ($exact->count() > 1) {
            return null;
        }

        $lower = TinhThanh::query()
            ->whereRaw('LOWER(full_name) = ?', [mb_strtolower($name)])
            ->get();

        return $lower->count() === 1 ? $lower->first() : null;
    }

    private function findLegacyWard(string $wardName, ?string $districtCode, string $districtName = ''): ?XaPhuongCu
    {
        $query = XaPhuongCu::query()->where('full_name', $wardName);

        if ($districtCode) {
            $query->where('quan_huyen_cu_code', $districtCode);
        } elseif ($districtName !== '') {
            $query->whereHas('quanHuyen', fn ($q) => $q->where('full_name', $districtName));
        }

        $exact = $query->get();
        if ($exact->count() === 1) {
            return $exact->first();
        }
        if ($exact->count() > 1) {
            return null;
        }

        $lowerQuery = XaPhuongCu::query()
            ->whereRaw('LOWER(full_name) = ?', [mb_strtolower($wardName)]);

        if ($districtCode) {
            $lowerQuery->where('quan_huyen_cu_code', $districtCode);
        } elseif ($districtName !== '') {
            $lowerQuery->whereHas(
                'quanHuyen',
                fn ($q) => $q->whereRaw('LOWER(full_name) = ?', [mb_strtolower($districtName)]),
            );
        }

        $lower = $lowerQuery->get();

        return $lower->count() === 1 ? $lower->first() : null;
    }

    private function findNewWard(string $wardName, ?string $provinceCode): ?XaPhuong
    {
        $query = XaPhuong::query()->where('full_name', $wardName);
        if ($provinceCode) {
            $query->where('tinh_thanh_code', $provinceCode);
        }

        $exact = $query->get();
        if ($exact->count() === 1) {
            return $exact->first();
        }
        if ($exact->count() > 1) {
            return null;
        }

        $lowerQuery = XaPhuong::query()
            ->whereRaw('LOWER(full_name) = ?', [mb_strtolower($wardName)]);
        if ($provinceCode) {
            $lowerQuery->where('tinh_thanh_code', $provinceCode);
        }

        $lower = $lowerQuery->get();

        return $lower->count() === 1 ? $lower->first() : null;
    }
}
