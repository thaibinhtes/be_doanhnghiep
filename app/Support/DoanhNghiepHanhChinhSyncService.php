<?php

namespace App\Support;

use App\Models\HanhChinhPhuongXa;
use App\Models\HanhChinhQuanHuyen;
use App\Models\HanhChinhTinh;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Đồng bộ text hành chính trên doanh nghiệp sang 3 bảng danh mục hợp nhất
 * (hanh_chinh_tinh / hanh_chinh_quan_huyen / hanh_chinh_phuong_xa, loai cu|moi),
 * sau đó gán id liên kết từng field. Field đã có id (đã sync) sẽ bỏ qua.
 */
class DoanhNghiepHanhChinhSyncService
{
    /**
     * @var array<string, array{label: string, text: string, id: string, cap: string, loai: string, parentId: string|null}>
     */
    private const FIELD_CONFIG = [
        'tinhThanhCu' => [
            'label' => 'Tỉnh / Thành phố cũ',
            'text' => 'tinh_thanh_cu',
            'id' => 'tinh_thanh_cu_id',
            'cap' => 'tinh',
            'loai' => 'cu',
            'parentId' => null,
        ],
        'tinhThanhMoi' => [
            'label' => 'Tỉnh / Thành phố mới',
            'text' => 'tinh_thanh_moi',
            'id' => 'tinh_thanh_moi_id',
            'cap' => 'tinh',
            'loai' => 'moi',
            'parentId' => null,
        ],
        'quanHuyenCu' => [
            'label' => 'Quận / Huyện cũ',
            'text' => 'quan_huyen_cu',
            'id' => 'quan_huyen_cu_id',
            'cap' => 'quan_huyen',
            'loai' => 'cu',
            'parentId' => 'tinh_thanh_cu_id',
        ],
        'quanHuyenMoi' => [
            'label' => 'Quận / Huyện mới',
            'text' => 'quan_huyen_moi',
            'id' => 'quan_huyen_moi_id',
            'cap' => 'quan_huyen',
            'loai' => 'moi',
            'parentId' => 'tinh_thanh_moi_id',
        ],
        'phuongXaCu' => [
            'label' => 'Phường / Xã cũ',
            'text' => 'xa_phuong_cu',
            'id' => 'xa_phuong_cu_id',
            'cap' => 'phuong_xa',
            'loai' => 'cu',
            'parentId' => 'quan_huyen_cu_id',
        ],
        'phuongXaMoi' => [
            'label' => 'Phường / Xã mới',
            'text' => 'xa_phuong_moi',
            'id' => 'xa_phuong_moi_id',
            'cap' => 'phuong_xa',
            'loai' => 'moi',
            'parentId' => 'quan_huyen_moi_id',
        ],
    ];

    /** @var array<string, int> key "loai|parentId|ten" → id */
    private array $tinhMap = [];

    private array $quanHuyenMap = [];

    private array $phuongXaMap = [];

    /** @var array<string, int> fallback theo tên: "loai|ten" → id */
    private array $quanHuyenNameMap = [];

    private array $phuongXaNameMap = [];

    private int $nextDryRunId = -1;

    /**
     * @return array<string, mixed>
     */
    public function sync(bool $dryRun = false, ?User $user = null): array
    {
        $this->loadCatalogs();

        $result = [
            'scanned' => 0,
            'alreadySynced' => 0,
            'updatedCompanies' => 0,
            'createdTinh' => 0,
            'createdQuanHuyen' => 0,
            'createdPhuongXa' => 0,
        ];

        DoanhNghiepScopeHelper::query($user)
            ->where(function ($query) {
                $query
                    ->whereNotNull('tinh_thanh_cu')
                    ->orWhereNotNull('tinh_thanh_moi')
                    ->orWhereNotNull('quan_huyen_cu')
                    ->orWhereNotNull('xa_phuong_cu')
                    ->orWhereNotNull('quan_huyen_moi')
                    ->orWhereNotNull('xa_phuong_moi');
            })
            ->orderBy('id')
            ->chunkById(300, function ($companies) use (&$result, $dryRun) {
                foreach ($companies as $company) {
                    $result['scanned']++;
                    $updates = [];

                    $tinhCuId = $company->tinh_thanh_cu_id;
                    if ($tinhCuId === null && $this->hasText($company->tinh_thanh_cu)) {
                        $tinhCuId = $this->resolveTinh($company->tinh_thanh_cu, 'cu', $dryRun, $result);
                        $updates['tinh_thanh_cu_id'] = $tinhCuId;
                    }

                    $tinhMoiId = $company->tinh_thanh_moi_id;
                    if ($tinhMoiId === null && $this->hasText($company->tinh_thanh_moi)) {
                        $tinhMoiId = $this->resolveTinh($company->tinh_thanh_moi, 'moi', $dryRun, $result);
                        $updates['tinh_thanh_moi_id'] = $tinhMoiId;
                    }

                    $quanCuId = $company->quan_huyen_cu_id;
                    if ($quanCuId === null && $this->hasText($company->quan_huyen_cu)) {
                        $quanCuId = $this->resolveQuanHuyen($company->quan_huyen_cu, 'cu', $tinhCuId, $dryRun, $result);
                        $updates['quan_huyen_cu_id'] = $quanCuId;
                    }

                    if ($company->xa_phuong_cu_id === null && $this->hasText($company->xa_phuong_cu)) {
                        $updates['xa_phuong_cu_id'] = $this->resolvePhuongXa($company->xa_phuong_cu, 'cu', $quanCuId, $dryRun, $result);
                    }

                    $quanMoiId = $company->quan_huyen_moi_id;
                    if ($quanMoiId === null && $this->hasText($company->quan_huyen_moi)) {
                        $quanMoiId = $this->resolveQuanHuyen($company->quan_huyen_moi, 'moi', $tinhMoiId, $dryRun, $result);
                        $updates['quan_huyen_moi_id'] = $quanMoiId;
                    }

                    if ($company->xa_phuong_moi_id === null && $this->hasText($company->xa_phuong_moi)) {
                        $updates['xa_phuong_moi_id'] = $this->resolvePhuongXa($company->xa_phuong_moi, 'moi', $quanMoiId, $dryRun, $result);
                    }

                    if ($updates === []) {
                        $result['alreadySynced']++;

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

        return $result;
    }

    /**
     * @return array<int, array{key: string, label: string, catalog: string, loai: string}>
     */
    public function fieldSyncOptions(): array
    {
        $catalogLabels = [
            'tinh' => 'Bảng tỉnh',
            'quan_huyen' => 'Bảng quận huyện',
            'phuong_xa' => 'Bảng phường xã',
        ];

        return collect(self::FIELD_CONFIG)
            ->map(fn (array $config, string $key) => [
                'key' => $key,
                'label' => $config['label'],
                'catalog' => $catalogLabels[$config['cap']],
                'loai' => $config['loai'],
            ])
            ->values()
            ->all();
    }

    /**
     * Đồng bộ riêng một field text vào đúng bảng danh mục và loại cũ/mới.
     *
     * @return array<string, mixed>
     */
    public function syncField(string $field, bool $dryRun = false, ?User $user = null): array
    {
        $config = self::FIELD_CONFIG[$field] ?? null;
        if ($config === null) {
            throw new \InvalidArgumentException('Field hành chính không được hỗ trợ đồng bộ.');
        }

        $this->loadCatalogs();
        $result = [
            'field' => $field,
            'label' => $config['label'],
            'catalog' => $config['cap'],
            'loai' => $config['loai'],
            'scanned' => 0,
            'matched' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'alreadyLinked' => 0,
            'unmapped' => [],
        ];

        DoanhNghiepScopeHelper::query($user)
            ->orderBy('id')
            ->chunkById(300, function ($companies) use ($config, $dryRun, &$result) {
                foreach ($companies as $company) {
                    $result['scanned']++;

                    if ($company->{$config['id']} !== null) {
                        $result['alreadyLinked']++;

                        continue;
                    }

                    $text = HanhChinhCodeGenerator::normalizeName((string) ($company->{$config['text']} ?? ''));
                    if ($text === '') {
                        $result['skipped']++;

                        continue;
                    }

                    $beforeCreated = $result['created'];
                    $resolveResult = ['createdTinh' => 0, 'createdQuanHuyen' => 0, 'createdPhuongXa' => 0];
                    $parentId = $config['parentId'] !== null ? $company->{$config['parentId']} : null;

                    $id = match ($config['cap']) {
                        'tinh' => $this->resolveTinh($text, $config['loai'], $dryRun, $resolveResult),
                        'quan_huyen' => $this->resolveQuanHuyen(
                            $text,
                            $config['loai'],
                            $parentId,
                            $dryRun,
                            $resolveResult,
                        ),
                        default => $this->resolvePhuongXa(
                            $text,
                            $config['loai'],
                            $parentId,
                            $dryRun,
                            $resolveResult,
                        ),
                    };

                    $created = array_sum($resolveResult);
                    $result['created'] += $created;
                    if ($result['created'] === $beforeCreated) {
                        $result['matched']++;
                    }
                    $result['updated']++;

                    if (! $dryRun) {
                        DB::table('doanh_nghieps')
                            ->where('id', $company->id)
                            ->update([
                                $config['id'] => $id,
                                'hanh_chinh_synced_at' => now(),
                                'updated_at' => now(),
                            ]);
                    }
                }
            });

        return $result;
    }

    private function loadCatalogs(): void
    {
        foreach (HanhChinhTinh::query()->orderBy('id')->get(['id', 'ten', 'loai']) as $item) {
            $this->tinhMap[$this->key($item->loai, null, $item->ten)] ??= $item->id;
        }

        foreach (HanhChinhQuanHuyen::query()->orderBy('id')->get(['id', 'ten', 'loai', 'tinh_id']) as $item) {
            $this->quanHuyenMap[$this->key($item->loai, $item->tinh_id, $item->ten)] ??= $item->id;
            $this->quanHuyenNameMap[$this->key($item->loai, null, $item->ten)] ??= $item->id;
        }

        foreach (HanhChinhPhuongXa::query()->orderBy('id')->get(['id', 'ten', 'loai', 'quan_huyen_id']) as $item) {
            $this->phuongXaMap[$this->key($item->loai, $item->quan_huyen_id, $item->ten)] ??= $item->id;
            $this->phuongXaNameMap[$this->key($item->loai, null, $item->ten)] ??= $item->id;
        }
    }

    private function resolveTinh(string $text, string $loai, bool $dryRun, array &$result): int
    {
        $name = HanhChinhCodeGenerator::normalizeName($text);
        $key = $this->key($loai, null, $name);

        if (isset($this->tinhMap[$key])) {
            return $this->tinhMap[$key];
        }

        $id = $dryRun
            ? $this->nextDryRunId--
            : HanhChinhTinh::query()->create(['ten' => $name, 'loai' => $loai])->id;

        $this->tinhMap[$key] = $id;
        $result['createdTinh']++;

        return $id;
    }

    private function resolveQuanHuyen(string $text, string $loai, ?int $tinhId, bool $dryRun, array &$result): int
    {
        $name = HanhChinhCodeGenerator::normalizeName($text);
        $parentKey = $this->key($loai, $tinhId, $name);
        $nameKey = $this->key($loai, null, $name);

        if (isset($this->quanHuyenMap[$parentKey])) {
            return $this->quanHuyenMap[$parentKey];
        }
        if (isset($this->quanHuyenNameMap[$nameKey])) {
            return $this->quanHuyenNameMap[$nameKey];
        }

        $id = $dryRun
            ? $this->nextDryRunId--
            : HanhChinhQuanHuyen::query()->create([
                'ten' => $name,
                'loai' => $loai,
                'tinh_id' => $tinhId !== null && $tinhId > 0 ? $tinhId : null,
            ])->id;

        $this->quanHuyenMap[$parentKey] = $id;
        $this->quanHuyenNameMap[$nameKey] = $id;
        $result['createdQuanHuyen']++;

        return $id;
    }

    private function resolvePhuongXa(string $text, string $loai, ?int $quanHuyenId, bool $dryRun, array &$result): int
    {
        $name = HanhChinhCodeGenerator::normalizeName($text);
        $parentKey = $this->key($loai, $quanHuyenId, $name);
        $nameKey = $this->key($loai, null, $name);

        if (isset($this->phuongXaMap[$parentKey])) {
            return $this->phuongXaMap[$parentKey];
        }
        if (isset($this->phuongXaNameMap[$nameKey])) {
            return $this->phuongXaNameMap[$nameKey];
        }

        $id = $dryRun
            ? $this->nextDryRunId--
            : HanhChinhPhuongXa::query()->create([
                'ten' => $name,
                'loai' => $loai,
                'quan_huyen_id' => $quanHuyenId !== null && $quanHuyenId > 0 ? $quanHuyenId : null,
            ])->id;

        $this->phuongXaMap[$parentKey] = $id;
        $this->phuongXaNameMap[$nameKey] = $id;
        $result['createdPhuongXa']++;

        return $id;
    }

    private function hasText(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    private function key(string $loai, ?int $parentId, string $name): string
    {
        return $loai.'|'.($parentId ?? '').'|'.mb_strtolower(HanhChinhCodeGenerator::normalizeName($name));
    }
}
