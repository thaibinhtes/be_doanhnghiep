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
                    'new_unit_type' => $row['loaiMoi'] ?? $row['loai_moi'] ?? null,
                    'notes' => $row['notes'] ?? null,
                ];

                $this->upsertMappingPair($xaCuCode, $xaMoi->code, $mappingPayload, $counts);
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
                    'new_unit_type' => is_string($loaiMoi) ? $loaiMoi : (is_scalar($loaiMoi) ? trim((string) $loaiMoi) : null),
                    'notes' => $row['notes'] ?? null,
                ];

                $this->upsertMappingPair($xaCu->code, $xaMoi->code, $mappingPayload, $counts);
            }
        });

        return $counts;
    }

    /**
     * Liên kết nhiều đơn vị cũ với một đơn vị mới (many-to-many).
     * Một đơn vị cũ có thể liên kết nhiều đơn vị mới và ngược lại.
     *
     * @param  list<string>  $xaPhuongCuCodes
     * @param  list<string>|null  $syncScopeCuCodes  Phạm vi đồng bộ: bỏ liên kết (cũ→mới) nếu không còn được chọn.
     * @return array{created: int, updated: int, deleted: int}
     */
    public function linkLegacyToNew(
        array $xaPhuongCuCodes,
        string $xaPhuongMoiCode,
        ?int $groupNo = null,
        ?string $newUnitType = null,
        ?string $notes = null,
        ?array $syncScopeCuCodes = null,
    ): array {
        $created = 0;
        $updated = 0;
        $deleted = 0;

        DB::transaction(function () use ($xaPhuongCuCodes, $xaPhuongMoiCode, $groupNo, $newUnitType, $notes, $syncScopeCuCodes, &$created, &$updated, &$deleted) {
            $codesToLink = array_values(array_unique($xaPhuongCuCodes));
            $counts = ['mappings' => 0, 'mappingsUpdated' => 0];

            foreach ($codesToLink as $code) {
                $this->upsertMappingPair($code, $xaPhuongMoiCode, [
                    'group_no' => $groupNo,
                    'new_unit_type' => $newUnitType,
                    'notes' => $notes,
                ], $counts);
            }

            $created = $counts['mappings'];
            $updated = $counts['mappingsUpdated'];

            if ($syncScopeCuCodes !== null) {
                $scope = array_values(array_unique($syncScopeCuCodes));
                $toUnlink = array_diff($scope, $codesToLink);

                foreach ($toUnlink as $code) {
                    $deleted += HanhChinhMapping::query()
                        ->where('xa_phuong_cu_code', $code)
                        ->where('xa_phuong_moi_code', $xaPhuongMoiCode)
                        ->delete();
                }
            }
        });

        return compact('created', 'updated', 'deleted');
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

                $mappings = HanhChinhMapping::query()
                    ->with(['xaPhuongMoi.tinhThanh'])
                    ->where('xa_phuong_cu_code', $xaCu->code)
                    ->get();

                if ($mappings->isEmpty()) {
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

                $mapping = null;
                if ($company->xa_phuong_code) {
                    $mapping = $mappings->firstWhere('xa_phuong_moi_code', $company->xa_phuong_code);
                }
                if (!$mapping && $mappings->count() === 1) {
                    $mapping = $mappings->first();
                }

                if (!$mapping?->xaPhuongMoi) {
                    $newUnitNames = $mappings
                        ->map(fn (HanhChinhMapping $item) => $item->xaPhuongMoi?->full_name)
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    $result['unmapped'][] = [
                        'id' => $company->id,
                        'maSoDoanhNghiep' => $company->ma_so_doanh_nghiep,
                        'quanHuyen' => $company->quan_huyen,
                        'phuongXa' => $company->phuong_xa,
                        'xaPhuongCuCode' => $xaCu->code,
                        'reason' => count($newUnitNames) > 1
                            ? 'Nhiều đơn vị mới: ' . implode(', ', $newUnitNames)
                            : 'Chưa xác định được đơn vị mới',
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

    /**
     * @return array<int, array{key: string, label: string, sources: array<int, array{key: string, label: string}>}>
     */
    public function companyFieldSyncOptions(): array
    {
        return [
            [
                'key' => 'quanHuyen',
                'label' => 'Quận / Huyện',
                'sources' => [
                    ['key' => 'hanh_chinh_cu', 'label' => 'Hành chính cũ (quận/huyện)'],
                    ['key' => 'hanh_chinh_moi', 'label' => 'Hành chính mới (tỉnh/thành)'],
                ],
            ],
            [
                'key' => 'phuongXa',
                'label' => 'Phường / Xã',
                'sources' => [
                    ['key' => 'hanh_chinh_cu', 'label' => 'Hành chính cũ (phường/xã)'],
                    ['key' => 'hanh_chinh_moi', 'label' => 'Hành chính mới (phường/xã)'],
                ],
            ],
        ];
    }

    /**
     * @return array{
     *     field: string,
     *     sourceTable: string,
     *     matched: int,
     *     updated: int,
     *     skipped: int,
     *     alreadyLinked: int,
     *     unmapped: array<int, array<string, mixed>>
     * }
     */
    public function syncCompanyField(string $field, string $sourceTable, bool $dryRun = false, ?User $user = null): array
    {
        if (!in_array($field, ['quanHuyen', 'phuongXa'], true)) {
            throw new \InvalidArgumentException('Field không được hỗ trợ đồng bộ.');
        }

        if (!in_array($sourceTable, ['hanh_chinh_cu', 'hanh_chinh_moi'], true)) {
            throw new \InvalidArgumentException('Bảng nguồn không hợp lệ.');
        }

        $result = [
            'field' => $field,
            'sourceTable' => $sourceTable,
            'matched' => 0,
            'updated' => 0,
            'skipped' => 0,
            'alreadyLinked' => 0,
            'unmapped' => [],
        ];

        if ($field === 'quanHuyen') {
            return $this->syncCompanyQuanHuyenField($sourceTable, $dryRun, $user, $result);
        }

        return $this->syncCompanyPhuongXaField($sourceTable, $dryRun, $user, $result);
    }

    /**
     * @param  array{
     *     field: string,
     *     sourceTable: string,
     *     matched: int,
     *     updated: int,
     *     skipped: int,
     *     alreadyLinked: int,
     *     unmapped: array<int, array<string, mixed>>
     * }  $result
     * @return array{
     *     field: string,
     *     sourceTable: string,
     *     matched: int,
     *     updated: int,
     *     skipped: int,
     *     alreadyLinked: int,
     *     unmapped: array<int, array<string, mixed>>
     * }
     */
    private function syncCompanyQuanHuyenField(string $sourceTable, bool $dryRun, ?User $user, array $result): array
    {
        $codeColumn = $sourceTable === 'hanh_chinh_cu' ? 'quan_huyen_cu_code' : 'tinh_thanh_code';

        DoanhNghiepScopeHelper::query($user)->orderBy('id')->chunkById(200, function ($companies) use (
            $dryRun,
            $sourceTable,
            $codeColumn,
            &$result
        ): void {
            foreach ($companies as $company) {
                $textValue = HanhChinhCodeGenerator::normalizeName((string) ($company->quan_huyen ?? ''));

                if ($textValue === '') {
                    $result['skipped']++;
                    continue;
                }

                if ($company->{$codeColumn}) {
                    $result['alreadyLinked']++;
                    continue;
                }

                $matchedUnit = $sourceTable === 'hanh_chinh_cu'
                    ? $this->findLegacyDistrict($textValue)
                    : $this->findNewProvince($textValue);

                if (!$matchedUnit) {
                    $result['unmapped'][] = [
                        'id' => $company->id,
                        'maSoDoanhNghiep' => $company->ma_so_doanh_nghiep,
                        'quanHuyen' => $company->quan_huyen,
                        'phuongXa' => $company->phuong_xa,
                        'reason' => $sourceTable === 'hanh_chinh_cu'
                            ? 'Không khớp quận/huyện trong hành chính cũ'
                            : 'Không khớp tỉnh/thành trong hành chính mới',
                    ];
                    continue;
                }

                $result['matched']++;

                if ($dryRun) {
                    $result['updated']++;
                    continue;
                }

                $company->update([
                    $codeColumn => $matchedUnit->code,
                ]);

                $result['updated']++;
            }
        });

        return $result;
    }

    /**
     * @param  array{
     *     field: string,
     *     sourceTable: string,
     *     matched: int,
     *     updated: int,
     *     skipped: int,
     *     alreadyLinked: int,
     *     unmapped: array<int, array<string, mixed>>
     * }  $result
     * @return array{
     *     field: string,
     *     sourceTable: string,
     *     matched: int,
     *     updated: int,
     *     skipped: int,
     *     alreadyLinked: int,
     *     unmapped: array<int, array<string, mixed>>
     * }
     */
    private function syncCompanyPhuongXaField(string $sourceTable, bool $dryRun, ?User $user, array $result): array
    {
        $codeColumn = $sourceTable === 'hanh_chinh_cu' ? 'xa_phuong_cu_code' : 'xa_phuong_code';

        DoanhNghiepScopeHelper::query($user)->orderBy('id')->chunkById(200, function ($companies) use (
            $dryRun,
            $sourceTable,
            $codeColumn,
            &$result
        ): void {
            foreach ($companies as $company) {
                $textValue = HanhChinhCodeGenerator::normalizeName((string) ($company->phuong_xa ?? ''));

                if ($textValue === '') {
                    $result['skipped']++;
                    continue;
                }

                if ($company->{$codeColumn}) {
                    $result['alreadyLinked']++;
                    continue;
                }

                $matchedUnit = $sourceTable === 'hanh_chinh_cu'
                    ? $this->findLegacyWardForFieldSync($company, $textValue)
                    : $this->findNewWardForFieldSync($company, $textValue);

                if (!$matchedUnit) {
                    $result['unmapped'][] = [
                        'id' => $company->id,
                        'maSoDoanhNghiep' => $company->ma_so_doanh_nghiep,
                        'quanHuyen' => $company->quan_huyen,
                        'phuongXa' => $company->phuong_xa,
                        'reason' => $sourceTable === 'hanh_chinh_cu'
                            ? 'Không khớp phường/xã trong hành chính cũ'
                            : 'Không khớp phường/xã trong hành chính mới',
                    ];
                    continue;
                }

                $result['matched']++;

                if ($dryRun) {
                    $result['updated']++;
                    continue;
                }

                $payload = [$codeColumn => $matchedUnit->code];

                if ($sourceTable === 'hanh_chinh_cu' && !$company->quan_huyen_cu_code && $matchedUnit->quan_huyen_cu_code) {
                    $payload['quan_huyen_cu_code'] = $matchedUnit->quan_huyen_cu_code;
                }

                if ($sourceTable === 'hanh_chinh_moi' && !$company->tinh_thanh_code && $matchedUnit->tinh_thanh_code) {
                    $payload['tinh_thanh_code'] = $matchedUnit->tinh_thanh_code;
                }

                $company->update($payload);

                $result['updated']++;
            }
        });

        return $result;
    }

    private function findLegacyDistrict(string $districtName): ?QuanHuyenCu
    {
        $districts = QuanHuyenCu::query()
            ->where('full_name', $districtName)
            ->get();

        if ($districts->count() === 1) {
            return $districts->first();
        }

        if ($districts->count() > 1) {
            return null;
        }

        $lowerMatches = QuanHuyenCu::query()
            ->whereRaw('LOWER(full_name) = ?', [mb_strtolower($districtName)])
            ->get();

        return $lowerMatches->count() === 1 ? $lowerMatches->first() : null;
    }

    private function findNewProvince(string $provinceName): ?TinhThanh
    {
        $provinces = TinhThanh::query()
            ->where('full_name', $provinceName)
            ->get();

        if ($provinces->count() === 1) {
            return $provinces->first();
        }

        if ($provinces->count() > 1) {
            return null;
        }

        $lowerMatches = TinhThanh::query()
            ->whereRaw('LOWER(full_name) = ?', [mb_strtolower($provinceName)])
            ->get();

        return $lowerMatches->count() === 1 ? $lowerMatches->first() : null;
    }

    private function findLegacyWardForFieldSync(DoanhNghiep $company, string $wardName): ?XaPhuongCu
    {
        $query = XaPhuongCu::query()->where('full_name', $wardName);

        if ($company->quan_huyen_cu_code) {
            $query->where('quan_huyen_cu_code', $company->quan_huyen_cu_code);
        } else {
            $districtName = HanhChinhCodeGenerator::normalizeName((string) ($company->quan_huyen ?? ''));
            if ($districtName !== '') {
                $query->whereHas('quanHuyen', fn ($q) => $q->where('full_name', $districtName));
            }
        }

        $matches = $query->get();
        if ($matches->count() === 1) {
            return $matches->first();
        }

        if ($matches->count() > 1) {
            return null;
        }

        $lowerQuery = XaPhuongCu::query()
            ->whereRaw('LOWER(full_name) = ?', [mb_strtolower($wardName)]);

        if ($company->quan_huyen_cu_code) {
            $lowerQuery->where('quan_huyen_cu_code', $company->quan_huyen_cu_code);
        } else {
            $districtName = HanhChinhCodeGenerator::normalizeName((string) ($company->quan_huyen ?? ''));
            if ($districtName !== '') {
                $lowerQuery->whereHas(
                    'quanHuyen',
                    fn ($q) => $q->whereRaw('LOWER(full_name) = ?', [mb_strtolower($districtName)]),
                );
            }
        }

        $lowerMatches = $lowerQuery->get();

        return $lowerMatches->count() === 1 ? $lowerMatches->first() : null;
    }

    private function findNewWardForFieldSync(DoanhNghiep $company, string $wardName): ?XaPhuong
    {
        $query = XaPhuong::query()->where('full_name', $wardName);

        if ($company->tinh_thanh_code) {
            $query->where('tinh_thanh_code', $company->tinh_thanh_code);
        }

        $matches = $query->get();
        if ($matches->count() === 1) {
            return $matches->first();
        }

        if ($matches->count() > 1) {
            return null;
        }

        $lowerQuery = XaPhuong::query()
            ->whereRaw('LOWER(full_name) = ?', [mb_strtolower($wardName)]);

        if ($company->tinh_thanh_code) {
            $lowerQuery->where('tinh_thanh_code', $company->tinh_thanh_code);
        }

        $lowerMatches = $lowerQuery->get();

        return $lowerMatches->count() === 1 ? $lowerMatches->first() : null;
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

        if ($company->quan_huyen_cu_code) {
            return XaPhuongCu::query()
                ->where('full_name', $phuongXa)
                ->where('quan_huyen_cu_code', $company->quan_huyen_cu_code)
                ->first();
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

    /**
     * @param  array{mappings?: int, mappingsUpdated?: int}  $counts
     */
    private function upsertMappingPair(
        string $xaCuCode,
        string $xaMoiCode,
        array $payload,
        array &$counts,
    ): bool {
        $existing = HanhChinhMapping::query()
            ->where('xa_phuong_cu_code', $xaCuCode)
            ->where('xa_phuong_moi_code', $xaMoiCode)
            ->first();

        HanhChinhMapping::query()->updateOrCreate(
            [
                'xa_phuong_cu_code' => $xaCuCode,
                'xa_phuong_moi_code' => $xaMoiCode,
            ],
            $payload,
        );

        if ($existing) {
            $counts['mappingsUpdated'] = ($counts['mappingsUpdated'] ?? 0) + 1;
        } else {
            $counts['mappings'] = ($counts['mappings'] ?? 0) + 1;
        }

        return true;
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
