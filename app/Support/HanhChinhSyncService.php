<?php

namespace App\Support;

use App\Models\DoanhNghiep;
use App\Models\HanhChinhMapping;
use App\Models\User;
use App\Support\DoanhNghiepScopeHelper;
use App\Models\QuanHuyenCu;
use App\Models\TinhThanh;
use App\Models\TinhThanhCu;
use App\Models\XaPhuong;
use App\Models\XaPhuongCu;
use Illuminate\Support\Facades\DB;

class HanhChinhSyncService
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{provinces: int, districts: int, wards: int, mappings: int}
     */
    public function importLegacyWithMappings(array $rows): array
    {
        $counts = ['provinces' => 0, 'districts' => 0, 'wards' => 0, 'mappings' => 0];

        DB::transaction(function () use ($rows, &$counts) {
            foreach ($rows as $row) {
                $tinhName = HanhChinhCodeGenerator::normalizeName((string) ($row['tinhThanhCu'] ?? $row['tinh_thanh_cu'] ?? ''));
                $quanName = HanhChinhCodeGenerator::normalizeName((string) ($row['quanHuyenCu'] ?? $row['quan_huyen_cu'] ?? ''));
                $xaName = HanhChinhCodeGenerator::normalizeName((string) ($row['xaPhuongCu'] ?? $row['xa_phuong_cu'] ?? ''));
                $xaMoiName = HanhChinhCodeGenerator::normalizeName((string) ($row['xaPhuongMoi'] ?? $row['xa_phuong_moi'] ?? ''));
                $tinhMoiCode = isset($row['tinhThanhMoiCode']) ? (string) $row['tinhThanhMoiCode'] : (isset($row['tinh_thanh_moi_code']) ? (string) $row['tinh_thanh_moi_code'] : null);

                if ($tinhName === '' || $quanName === '' || $xaName === '' || $xaMoiName === '') {
                    continue;
                }

                $tinhCode = HanhChinhCodeGenerator::provinceCode(
                    $tinhName,
                    isset($row['tinhThanhCuCode']) ? (string) $row['tinhThanhCuCode'] : null,
                );
                $quanCode = HanhChinhCodeGenerator::districtCode(
                    $tinhCode,
                    $quanName,
                    isset($row['quanHuyenCuCode']) ? (string) $row['quanHuyenCuCode'] : null,
                );
                $xaCuCode = HanhChinhCodeGenerator::wardCode(
                    $quanCode,
                    $xaName,
                    isset($row['xaPhuongCuCode']) ? (string) $row['xaPhuongCuCode'] : null,
                );

                $tinh = TinhThanhCu::query()->updateOrCreate(
                    ['code' => $tinhCode],
                    ['full_name' => $tinhName],
                );
                if ($tinh->wasRecentlyCreated) {
                    $counts['provinces']++;
                }

                $quan = QuanHuyenCu::query()->updateOrCreate(
                    ['code' => $quanCode],
                    ['full_name' => $quanName, 'tinh_thanh_cu_code' => $tinhCode],
                );
                if ($quan->wasRecentlyCreated) {
                    $counts['districts']++;
                }

                $loaiCu = $row['loaiCu'] ?? $row['loai_cu'] ?? null;
                $xaCu = XaPhuongCu::query()->updateOrCreate(
                    ['code' => $xaCuCode],
                    [
                        'full_name' => $xaName,
                        'unit_type' => is_string($loaiCu) ? $loaiCu : null,
                        'quan_huyen_cu_code' => $quanCode,
                    ],
                );
                if ($xaCu->wasRecentlyCreated) {
                    $counts['wards']++;
                }

                $xaMoi = $this->resolveNewWard($xaMoiName, $tinhMoiCode, $tinhName);
                if (!$xaMoi) {
                    continue;
                }

                $mappingPayload = [
                    'group_no' => isset($row['groupNo']) ? (int) $row['groupNo'] : (isset($row['group_no']) ? (int) $row['group_no'] : null),
                    'xa_phuong_moi_code' => $xaMoi->code,
                    'new_unit_type' => $row['loaiMoi'] ?? $row['loai_moi'] ?? null,
                    'notes' => $row['notes'] ?? null,
                ];

                $existing = HanhChinhMapping::query()->where('xa_phuong_cu_code', $xaCuCode)->first();
                HanhChinhMapping::query()->updateOrCreate(
                    ['xa_phuong_cu_code' => $xaCuCode],
                    $mappingPayload,
                );
                if (!$existing) {
                    $counts['mappings']++;
                }
            }
        });

        return $counts;
    }

    /**
     * @param  array<int, array{code?: string, fullName: string, wards?: array<int, array{code?: string, fullName: string}>}>  $provinces
     */
    public function importNewAdministrativeData(array $provinces): array
    {
        $counts = ['provinces' => 0, 'wards' => 0];

        DB::transaction(function () use ($provinces, &$counts) {
            foreach ($provinces as $provinceRow) {
                $provinceName = HanhChinhCodeGenerator::normalizeName((string) ($provinceRow['fullName'] ?? $provinceRow['full_name'] ?? ''));
                $provinceCode = (string) ($provinceRow['code'] ?? HanhChinhCodeGenerator::provinceCode($provinceName));

                if ($provinceName === '' || $provinceCode === '') {
                    continue;
                }

                $province = TinhThanh::query()->updateOrCreate(
                    ['code' => $provinceCode],
                    ['full_name' => $provinceName],
                );
                if ($province->wasRecentlyCreated) {
                    $counts['provinces']++;
                }

                foreach ($provinceRow['wards'] ?? $provinceRow['Wards'] ?? [] as $wardRow) {
                    $wardName = HanhChinhCodeGenerator::normalizeName((string) ($wardRow['fullName'] ?? $wardRow['full_name'] ?? ''));
                    $wardCode = (string) ($wardRow['code'] ?? HanhChinhCodeGenerator::wardCode($provinceCode, $wardName));

                    if ($wardName === '' || $wardCode === '') {
                        continue;
                    }

                    $ward = XaPhuong::query()->updateOrCreate(
                        ['code' => $wardCode],
                        [
                            'full_name' => $wardName,
                            'tinh_thanh_code' => $provinceCode,
                        ],
                    );
                    if ($ward->wasRecentlyCreated) {
                        $counts['wards']++;
                    }
                }
            }
        });

        return $counts;
    }

    /**
     * @return array{matched: int, updated: int, skipped: int, unmapped: array<int, array<string, mixed>>}
     */
    public function syncCompanies(bool $dryRun = false, ?User $user = null): array
    {
        $result = ['matched' => 0, 'updated' => 0, 'skipped' => 0, 'unmapped' => []];

        DoanhNghiepScopeHelper::query($user)->orderBy('id')->chunkById(200, function ($companies) use ($dryRun, &$result) {
            foreach ($companies as $company) {
                $xaCu = $this->matchCompanyToLegacyWard($company);

                if (!$xaCu) {
                    $result['unmapped'][] = [
                        'id' => $company->id,
                        'maSoDoanhNghiep' => $company->ma_so_doanh_nghiep,
                        'quanHuyen' => $company->quan_huyen,
                        'phuongXa' => $company->phuong_xa,
                        'reason' => 'Không khớp đơn vị hành chính cũ',
                    ];
                    continue;
                }

                $result['matched']++;

                $mapping = HanhChinhMapping::query()
                    ->with(['xaPhuongMoi.tinhThanh'])
                    ->where('xa_phuong_cu_code', $xaCu->code)
                    ->first();

                if (!$mapping?->xaPhuongMoi) {
                    $result['unmapped'][] = [
                        'id' => $company->id,
                        'maSoDoanhNghiep' => $company->ma_so_doanh_nghiep,
                        'quanHuyen' => $company->quan_huyen,
                        'phuongXa' => $company->phuong_xa,
                        'xaPhuongCuCode' => $xaCu->code,
                        'reason' => 'Chưa có mapping sang đơn vị mới',
                    ];
                    continue;
                }

                if ($dryRun) {
                    $result['updated']++;
                    continue;
                }

                $xaMoi = $mapping->xaPhuongMoi;
                $tinhMoi = $xaMoi->tinhThanh;

                $company->update([
                    'xa_phuong_cu_code' => $xaCu->code,
                    'tinh_thanh_code' => $tinhMoi?->code,
                    'xa_phuong_code' => $xaMoi->code,
                    'quan_huyen' => $tinhMoi?->full_name ?? $company->quan_huyen,
                    'phuong_xa' => $xaMoi->full_name,
                    'hanh_chinh_synced_at' => now(),
                ]);

                $result['updated']++;
            }
        });

        return $result;
    }

    public function matchCompanyToLegacyWard(DoanhNghiep $company): ?XaPhuongCu
    {
        if ($company->xa_phuong_cu_code) {
            return XaPhuongCu::query()->find($company->xa_phuong_cu_code);
        }

        $quanHuyen = HanhChinhCodeGenerator::normalizeName((string) ($company->quan_huyen ?? ''));
        $phuongXa = HanhChinhCodeGenerator::normalizeName((string) ($company->phuong_xa ?? ''));

        if ($phuongXa === '') {
            return null;
        }

        $query = XaPhuongCu::query()->where('full_name', $phuongXa);

        if ($quanHuyen !== '') {
            $query->where(function ($builder) use ($quanHuyen) {
                $builder
                    ->whereHas('quanHuyen', fn ($q) => $q->where('full_name', $quanHuyen))
                    ->orWhereHas('quanHuyen.tinhThanh', fn ($q) => $q->where('full_name', $quanHuyen));
            });
        }

        return $query->with('quanHuyen.tinhThanh')->first();
    }

    private function resolveNewWard(string $wardName, ?string $provinceCode, string $legacyProvinceName): ?XaPhuong
    {
        if ($provinceCode) {
            $ward = XaPhuong::query()
                ->where('tinh_thanh_code', $provinceCode)
                ->where('full_name', $wardName)
                ->first();

            if ($ward) {
                return $ward;
            }
        }

        return XaPhuong::query()
            ->where('full_name', $wardName)
            ->whereHas('tinhThanh', function ($q) use ($legacyProvinceName) {
                $q->where('full_name', 'like', '%' . $legacyProvinceName . '%');
            })
            ->first();
    }
}
