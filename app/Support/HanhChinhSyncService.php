<?php

namespace App\Support;

use App\Models\DoanhNghiep;
use App\Models\HanhChinhMapping;
use App\Models\TinhThanhCu;
use App\Models\User;
use App\Support\DoanhNghiepScopeHelper;
use App\Models\QuanHuyenCu;
use App\Models\TinhThanh;
use App\Models\XaPhuong;
use App\Models\XaPhuongCu;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class HanhChinhSyncService
{
    private const LEGACY_PROVINCE_FALLBACK_CODE = 'CU-AG';
    private const LEGACY_PROVINCE_FALLBACK_NAME = 'An Giang';

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{provinces: int, districts: int, wards: int, mappings: int}
     */
    public function importLegacyWithMappings(array $rows): array
    {
        $counts = [
            'provinces' => 0,
            'districts' => 0,
            'districtsUpdated' => 0,
            'wards' => 0,
            'wardsUpdated' => 0,
            'mappings' => 0,
            'mappingsUpdated' => 0,
            'skipped' => 0,
        ];

        $rows = HanhChinhImportColumnMap::forwardFillRows($rows);

        DB::transaction(function () use ($rows, &$counts) {
            foreach ($rows as $row) {
                $quanName = HanhChinhCodeGenerator::normalizeName((string) ($row['quanHuyenCu'] ?? $row['quan_huyen_cu'] ?? ''));
                $xaName = HanhChinhCodeGenerator::normalizeName((string) ($row['xaPhuongCu'] ?? $row['xa_phuong_cu'] ?? ''));
                $xaMoiName = HanhChinhCodeGenerator::normalizeName((string) ($row['xaPhuongMoi'] ?? $row['xa_phuong_moi'] ?? ''));
                $tinhMoiCode = isset($row['tinhThanhMoiCode'])
                    ? (string) $row['tinhThanhMoiCode']
                    : (isset($row['tinh_thanh_moi_code']) ? (string) $row['tinh_thanh_moi_code'] : HanhChinhExcelColumns::DEFAULT_NEW_PROVINCE_CODE);

                if ($quanName === '' || $xaName === '') {
                    $counts['skipped']++;
                    continue;
                }

                $quanCode = HanhChinhCodeGenerator::districtCodeStandalone(
                    $quanName,
                    isset($row['quanHuyenCuCode']) ? (string) $row['quanHuyenCuCode'] : null,
                );
                $xaCuCode = HanhChinhCodeGenerator::wardCode(
                    $quanCode,
                    $xaName,
                    isset($row['xaPhuongCuCode']) ? (string) $row['xaPhuongCuCode'] : null,
                );

                $quan = $this->upsertLegacyDistrict($quanCode, $quanName);
                if ($quan->wasRecentlyCreated) {
                    $counts['districts']++;
                } else {
                    $counts['districtsUpdated']++;
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
                } else {
                    $counts['wardsUpdated']++;
                }

                if ($xaMoiName === '') {
                    continue;
                }

                $xaMoi = $this->resolveNewWard($xaMoiName, $tinhMoiCode);
                if (!$xaMoi) {
                    continue;
                }

                $groupNo = isset($row['groupNo'])
                    ? (int) $row['groupNo']
                    : (isset($row['group_no']) ? (int) $row['group_no'] : (isset($row['stt']) ? (int) $row['stt'] : null));

                $mappingPayload = [
                    'group_no' => $groupNo,
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
                } else {
                    $counts['mappingsUpdated']++;
                }
            }
        });

        return $counts;
    }

    /**
     * Chỉ tạo/cập nhật liên kết cũ → mới (đơn vị cũ và mới phải đã tồn tại).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{mappings: int, mappingsUpdated: int, skipped: int}
     */
    public function importMappingsOnly(array $rows): array
    {
        $counts = ['mappings' => 0, 'mappingsUpdated' => 0, 'skipped' => 0];

        $rows = HanhChinhImportColumnMap::forwardFillRows($rows);

        DB::transaction(function () use ($rows, &$counts) {
            foreach ($rows as $row) {
                $quanName = HanhChinhCodeGenerator::normalizeName((string) ($row['quanHuyenCu'] ?? $row['quan_huyen_cu'] ?? ''));
                $xaName = HanhChinhCodeGenerator::normalizeName((string) ($row['xaPhuongCu'] ?? $row['xa_phuong_cu'] ?? ''));
                $xaMoiName = HanhChinhCodeGenerator::normalizeName((string) ($row['xaPhuongMoi'] ?? $row['xa_phuong_moi'] ?? ''));
                $tinhMoiCode = isset($row['tinhThanhMoiCode'])
                    ? (string) $row['tinhThanhMoiCode']
                    : (isset($row['tinh_thanh_moi_code']) ? (string) $row['tinh_thanh_moi_code'] : HanhChinhExcelColumns::DEFAULT_NEW_PROVINCE_CODE);

                if ($xaName === '' || $xaMoiName === '') {
                    $counts['skipped']++;
                    continue;
                }

                $xaCu = $this->findLegacyWard($quanName, $xaName);
                $xaMoi = $this->resolveNewWard($xaMoiName, $tinhMoiCode);

                if (!$xaCu || !$xaMoi) {
                    $counts['skipped']++;
                    continue;
                }

                $groupNo = isset($row['groupNo'])
                    ? (int) $row['groupNo']
                    : (isset($row['group_no']) ? (int) $row['group_no'] : (isset($row['stt']) ? (int) $row['stt'] : null));

                $loaiMoi = HanhChinhExcelColumns::normalizeImportValue(
                    'loaiMoi',
                    $row['loaiMoi'] ?? $row['loai_moi'] ?? null,
                );

                $mappingPayload = [
                    'group_no' => $groupNo,
                    'xa_phuong_moi_code' => $xaMoi->code,
                    'new_unit_type' => is_string($loaiMoi) ? $loaiMoi : (is_scalar($loaiMoi) ? trim((string) $loaiMoi) : null),
                    'notes' => $row['notes'] ?? null,
                ];

                $existing = HanhChinhMapping::query()->where('xa_phuong_cu_code', $xaCu->code)->first();
                HanhChinhMapping::query()->updateOrCreate(
                    ['xa_phuong_cu_code' => $xaCu->code],
                    $mappingPayload,
                );

                if (!$existing) {
                    $counts['mappings']++;
                } else {
                    $counts['mappingsUpdated']++;
                }
            }
        });

        return $counts;
    }

    /**
     * Liên kết nhiều đơn vị cũ với một đơn vị mới.
     *
     * @param  list<string>  $xaPhuongCuCodes
     * @return array{created: int, updated: int}
     */
    public function linkLegacyToNew(
        array $xaPhuongCuCodes,
        string $xaPhuongMoiCode,
        ?int $groupNo = null,
        ?string $newUnitType = null,
        ?string $notes = null,
    ): array {
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($xaPhuongCuCodes, $xaPhuongMoiCode, $groupNo, $newUnitType, $notes, &$created, &$updated) {
            foreach ($xaPhuongCuCodes as $code) {
                $existing = HanhChinhMapping::query()->where('xa_phuong_cu_code', $code)->first();

                HanhChinhMapping::query()->updateOrCreate(
                    ['xa_phuong_cu_code' => $code],
                    [
                        'group_no' => $groupNo,
                        'xa_phuong_moi_code' => $xaPhuongMoiCode,
                        'new_unit_type' => $newUnitType,
                        'notes' => $notes,
                    ],
                );

                if ($existing) {
                    $updated++;
                } else {
                    $created++;
                }
            }
        });

        return compact('created', 'updated');
    }

    /**
     * Import đơn vị hành chính mới (2 cột: tên + loại). Không lưu tỉnh — mặc định An Giang (91).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{wards: int, wardsUpdated: int, skipped: int}
     */
    public function importNewUnitsOnly(array $rows): array
    {
        $counts = ['wards' => 0, 'wardsUpdated' => 0, 'skipped' => 0];
        $provinceCode = HanhChinhExcelColumns::DEFAULT_NEW_PROVINCE_CODE;

        DB::transaction(function () use ($rows, &$counts, $provinceCode) {
            $this->ensureDefaultProvince($provinceCode);

            foreach ($rows as $row) {
                $wardName = HanhChinhCodeGenerator::normalizeName((string) ($row['xaPhuongMoi'] ?? $row['xa_phuong_moi'] ?? ''));
                $unitType = HanhChinhExcelColumns::normalizeImportValue(
                    'loaiMoi',
                    $row['loaiMoi'] ?? $row['loai_moi'] ?? $row['loai'] ?? null,
                );

                if ($wardName === '') {
                    $counts['skipped']++;
                    continue;
                }

                $wardCode = HanhChinhCodeGenerator::wardCode(
                    $provinceCode,
                    $wardName,
                    isset($row['xaPhuongMoiCode']) ? (string) $row['xaPhuongMoiCode'] : null,
                );

                $ward = XaPhuong::query()->updateOrCreate(
                    ['code' => $wardCode],
                    [
                        'full_name' => $wardName,
                        'unit_type' => is_string($unitType) ? $unitType : (is_scalar($unitType) ? trim((string) $unitType) : null),
                        'tinh_thanh_code' => $provinceCode,
                    ],
                );

                if ($ward->wasRecentlyCreated) {
                    $counts['wards']++;
                } else {
                    $counts['wardsUpdated']++;
                }
            }
        });

        return $counts;
    }

    private function ensureDefaultProvince(string $provinceCode): void
    {
        TinhThanh::query()->updateOrCreate(
            ['code' => $provinceCode],
            ['full_name' => 'An Giang'],
        );
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
                            'unit_type' => $wardRow['unitType'] ?? $wardRow['unit_type'] ?? null,
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

        return $this->findLegacyWard($quanHuyen, $phuongXa);
    }

    private function findLegacyWard(string $districtName, string $wardName): ?XaPhuongCu
    {
        $query = XaPhuongCu::query()->where('full_name', $wardName);

        if ($districtName !== '') {
            $query->whereHas('quanHuyen', fn ($q) => $q->where('full_name', $districtName));
        }

        return $query->with('quanHuyen')->first();
    }

    private function resolveNewWard(string $wardName, ?string $provinceCode): ?XaPhuong
    {
        $provinceCode = $provinceCode ?: HanhChinhExcelColumns::DEFAULT_NEW_PROVINCE_CODE;

        $ward = XaPhuong::query()
            ->where('tinh_thanh_code', $provinceCode)
            ->where('full_name', $wardName)
            ->first();

        if ($ward) {
            return $ward;
        }

        return XaPhuong::query()
            ->where('full_name', $wardName)
            ->first();
    }

    private function upsertLegacyDistrict(string $quanCode, string $quanName): QuanHuyenCu
    {
        try {
            return QuanHuyenCu::query()->updateOrCreate(
                ['code' => $quanCode],
                ['full_name' => $quanName, 'tinh_thanh_cu_code' => null],
            );
        } catch (QueryException $exception) {
            if (!$this->isLegacyProvinceNotNullError($exception)) {
                throw $exception;
            }

            // Backward-compat fallback for databases that have not migrated to nullable province yet.
            $legacyProvinceCode = $this->ensureLegacyProvinceFallback();

            return QuanHuyenCu::query()->updateOrCreate(
                ['code' => $quanCode],
                ['full_name' => $quanName, 'tinh_thanh_cu_code' => $legacyProvinceCode],
            );
        }
    }

    private function ensureLegacyProvinceFallback(): string
    {
        TinhThanhCu::query()->updateOrCreate(
            ['code' => self::LEGACY_PROVINCE_FALLBACK_CODE],
            ['full_name' => self::LEGACY_PROVINCE_FALLBACK_NAME],
        );

        return self::LEGACY_PROVINCE_FALLBACK_CODE;
    }

    private function isLegacyProvinceNotNullError(QueryException $exception): bool
    {
        $message = (string) $exception->getMessage();

        return str_contains($message, 'quan_huyen_cu.tinh_thanh_cu_code')
            && str_contains($message, 'NOT NULL constraint failed');
    }
}
