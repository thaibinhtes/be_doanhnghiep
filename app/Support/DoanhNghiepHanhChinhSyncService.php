<?php

namespace App\Support;

use App\Models\QuanHuyenCu;
use App\Models\TinhThanh;
use App\Models\TinhThanhCu;
use App\Models\User;
use App\Models\XaPhuong;
use App\Models\XaPhuongCu;
use Illuminate\Support\Facades\DB;

class DoanhNghiepHanhChinhSyncService
{
    /**
     * Tạo/tìm danh mục từ các field text trên doanh nghiệp, sau đó chỉ ghi code.
     * Không thay đổi text nguồn hoặc địa chỉ hiển thị.
     *
     * @return array<string, mixed>
     */
    public function sync(bool $dryRun = false, ?User $user = null): array
    {
        $catalogs = $this->loadCatalogs();
        $result = [
            'scanned' => 0,
            'updatedCompanies' => 0,
            'createdLegacyProvinces' => 0,
            'createdLegacyDistricts' => 0,
            'createdLegacyWards' => 0,
            'createdNewProvinces' => 0,
            'createdNewWards' => 0,
            'skipped' => 0,
            'conflicts' => [],
        ];

        DoanhNghiepScopeHelper::query($user)
            ->where(function ($query) {
                $query
                    ->whereNotNull('tinh_thanh_cu')
                    ->orWhereNotNull('quan_huyen_cu')
                    ->orWhereNotNull('xa_phuong_cu')
                    ->orWhereNotNull('quan_huyen_moi')
                    ->orWhereNotNull('xa_phuong_moi');
            })
            ->orderBy('id')
            ->chunkById(300, function ($companies) use (&$catalogs, &$result, $dryRun) {
                foreach ($companies as $company) {
                    $result['scanned']++;
                    $updates = [];

                    $legacyProvinceCode = $this->resolveLegacyProvince(
                        (string) ($company->tinh_thanh_cu ?? ''),
                        $company->tinh_thanh_cu_code,
                        $catalogs,
                        $result,
                        $dryRun,
                    );
                    if ($legacyProvinceCode !== null && $legacyProvinceCode !== $company->tinh_thanh_cu_code) {
                        $updates['tinh_thanh_cu_code'] = $legacyProvinceCode;
                    }

                    $legacyDistrictCode = $this->resolveLegacyDistrict(
                        (string) ($company->quan_huyen_cu ?? ''),
                        $legacyProvinceCode,
                        $company->quan_huyen_cu_code,
                        $catalogs,
                        $result,
                        $dryRun,
                    );
                    if ($legacyDistrictCode !== null && $legacyDistrictCode !== $company->quan_huyen_cu_code) {
                        $updates['quan_huyen_cu_code'] = $legacyDistrictCode;
                    }

                    $legacyWardText = HanhChinhCodeGenerator::normalizeName((string) ($company->xa_phuong_cu ?? ''));
                    if ($legacyWardText !== '') {
                        if ($legacyDistrictCode === null) {
                            $this->recordConflict($result, $company->id, 'xa_phuong_cu', $legacyWardText, 'Thiếu cấp huyện cũ.');
                        } else {
                            $legacyWardCode = $this->resolveLegacyWard(
                                $legacyWardText,
                                $legacyDistrictCode,
                                $company->xa_phuong_cu_code,
                                $catalogs,
                                $result,
                                $dryRun,
                            );
                            if ($legacyWardCode !== $company->xa_phuong_cu_code) {
                                $updates['xa_phuong_cu_code'] = $legacyWardCode;
                            }
                        }
                    }

                    $newProvinceCode = $this->resolveNewProvince(
                        (string) ($company->quan_huyen_moi ?? ''),
                        $company->tinh_thanh_code,
                        $catalogs,
                        $result,
                        $dryRun,
                    );
                    if ($newProvinceCode !== null && $newProvinceCode !== $company->tinh_thanh_code) {
                        $updates['tinh_thanh_code'] = $newProvinceCode;
                    }

                    $newWardText = HanhChinhCodeGenerator::normalizeName((string) ($company->xa_phuong_moi ?? ''));
                    if ($newWardText !== '') {
                        if ($newProvinceCode === null) {
                            $this->recordConflict($result, $company->id, 'xa_phuong_moi', $newWardText, 'Thiếu cấp huyện/tỉnh mới.');
                        } else {
                            $newWardCode = $this->resolveNewWard(
                                $newWardText,
                                $newProvinceCode,
                                $company->xa_phuong_code,
                                $catalogs,
                                $result,
                                $dryRun,
                            );
                            if ($newWardCode !== $company->xa_phuong_code) {
                                $updates['xa_phuong_code'] = $newWardCode;
                            }
                        }
                    }

                    if ($updates === []) {
                        continue;
                    }

                    $result['updatedCompanies']++;
                    if (! $dryRun) {
                        DB::table('doanh_nghieps')
                            ->where('id', $company->id)
                            ->update([
                                ...$updates,
                                'hanh_chinh_synced_at' => now(),
                                'updated_at' => now(),
                            ]);
                    }
                }
            });

        $result['skipped'] = count($result['conflicts']);

        return $result;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function loadCatalogs(): array
    {
        return [
            'legacyProvinces' => TinhThanhCu::query()->get()->mapWithKeys(
                fn (TinhThanhCu $item) => [$this->key($item->full_name) => $item->code],
            )->all(),
            'legacyDistricts' => QuanHuyenCu::query()->get()->mapWithKeys(
                fn (QuanHuyenCu $item) => [$this->parentKey($item->tinh_thanh_cu_code, $item->full_name) => $item->code],
            )->all(),
            'legacyWards' => XaPhuongCu::query()->get()->mapWithKeys(
                fn (XaPhuongCu $item) => [$this->parentKey($item->quan_huyen_cu_code, $item->full_name) => $item->code],
            )->all(),
            'newProvinces' => TinhThanh::query()->get()->mapWithKeys(
                fn (TinhThanh $item) => [$this->key($item->full_name) => $item->code],
            )->all(),
            'newWards' => XaPhuong::query()->get()->mapWithKeys(
                fn (XaPhuong $item) => [$this->parentKey($item->tinh_thanh_code, $item->full_name) => $item->code],
            )->all(),
        ];
    }

    private function resolveLegacyProvince(
        string $text,
        ?string $existingCode,
        array &$catalogs,
        array &$result,
        bool $dryRun,
    ): ?string {
        $name = HanhChinhCodeGenerator::normalizeName($text);
        if ($name === '') {
            return $existingCode;
        }

        $key = $this->key($name);
        if (isset($catalogs['legacyProvinces'][$key])) {
            return $catalogs['legacyProvinces'][$key];
        }

        $code = HanhChinhCodeGenerator::provinceCode($name);
        if (! $dryRun) {
            TinhThanhCu::query()->firstOrCreate(['code' => $code], ['full_name' => $name]);
        }
        $catalogs['legacyProvinces'][$key] = $code;
        $result['createdLegacyProvinces']++;

        return $code;
    }

    private function resolveLegacyDistrict(
        string $text,
        ?string $provinceCode,
        ?string $existingCode,
        array &$catalogs,
        array &$result,
        bool $dryRun,
    ): ?string {
        $name = HanhChinhCodeGenerator::normalizeName($text);
        if ($name === '') {
            return $existingCode;
        }

        $key = $this->parentKey($provinceCode, $name);
        if (isset($catalogs['legacyDistricts'][$key])) {
            return $catalogs['legacyDistricts'][$key];
        }

        $code = HanhChinhCodeGenerator::districtCode((string) $provinceCode, $name);
        if (! $dryRun) {
            QuanHuyenCu::query()->firstOrCreate(
                ['code' => $code],
                ['full_name' => $name, 'tinh_thanh_cu_code' => $provinceCode],
            );
        }
        $catalogs['legacyDistricts'][$key] = $code;
        $result['createdLegacyDistricts']++;

        return $code;
    }

    private function resolveLegacyWard(
        string $name,
        string $districtCode,
        ?string $existingCode,
        array &$catalogs,
        array &$result,
        bool $dryRun,
    ): string {
        $key = $this->parentKey($districtCode, $name);
        if (isset($catalogs['legacyWards'][$key])) {
            return $catalogs['legacyWards'][$key];
        }

        $code = HanhChinhCodeGenerator::wardCode($districtCode, $name);
        if (! $dryRun) {
            XaPhuongCu::query()->firstOrCreate(
                ['code' => $code],
                ['full_name' => $name, 'quan_huyen_cu_code' => $districtCode],
            );
        }
        $catalogs['legacyWards'][$key] = $code;
        $result['createdLegacyWards']++;

        return $code;
    }

    private function resolveNewProvince(
        string $text,
        ?string $existingCode,
        array &$catalogs,
        array &$result,
        bool $dryRun,
    ): ?string {
        $name = HanhChinhCodeGenerator::normalizeName($text);
        if ($name === '') {
            return $existingCode;
        }

        $key = $this->key($name);
        if (isset($catalogs['newProvinces'][$key])) {
            return $catalogs['newProvinces'][$key];
        }

        $code = HanhChinhCodeGenerator::provinceCode($name);
        if (! $dryRun) {
            TinhThanh::query()->firstOrCreate(['code' => $code], ['full_name' => $name]);
        }
        $catalogs['newProvinces'][$key] = $code;
        $result['createdNewProvinces']++;

        return $code;
    }

    private function resolveNewWard(
        string $name,
        string $provinceCode,
        ?string $existingCode,
        array &$catalogs,
        array &$result,
        bool $dryRun,
    ): string {
        $key = $this->parentKey($provinceCode, $name);
        if (isset($catalogs['newWards'][$key])) {
            return $catalogs['newWards'][$key];
        }

        $code = HanhChinhCodeGenerator::wardCode($provinceCode, $name);
        if (! $dryRun) {
            XaPhuong::query()->firstOrCreate(
                ['code' => $code],
                ['full_name' => $name, 'tinh_thanh_code' => $provinceCode],
            );
        }
        $catalogs['newWards'][$key] = $code;
        $result['createdNewWards']++;

        return $code;
    }

    private function key(string $name): string
    {
        return mb_strtolower(HanhChinhCodeGenerator::normalizeName($name));
    }

    private function parentKey(?string $parentCode, string $name): string
    {
        return mb_strtolower((string) $parentCode).'|'.$this->key($name);
    }

    private function recordConflict(array &$result, int $companyId, string $field, string $value, string $reason): void
    {
        $result['conflicts'][] = compact('companyId', 'field', 'value', 'reason');
    }
}
