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

    /**
     * Group-by text thô trên doanh nghiệp → preview trước khi lưu vào bảng danh mục (loai cu|moi).
     *
     * @return array{
     *   field: string,
     *   label: string,
     *   catalog: string,
     *   loai: string,
     *   totalGroups: int,
     *   newGroups: int,
     *   existingGroups: int,
     *   totalCompanies: int,
     *   groups: array<int, array{ten: string, count: int, existsInCatalog: bool, existingId: int|null}>
     * }
     */
    public function previewRawGroups(string $field, ?User $user = null): array
    {
        $config = self::FIELD_CONFIG[$field] ?? null;
        if ($config === null) {
            throw new \InvalidArgumentException('Field hành chính không được hỗ trợ.');
        }

        $textCol = $config['text'];
        $rows = DoanhNghiepScopeHelper::query($user)
            ->whereNotNull($textCol)
            ->where($textCol, '!=', '')
            ->select($textCol)
            ->selectRaw('COUNT(*) as company_count')
            ->groupBy($textCol)
            ->orderByDesc('company_count')
            ->get();

        $merged = [];
        foreach ($rows as $row) {
            $ten = HanhChinhCodeGenerator::normalizeName((string) $row->{$textCol});
            if ($ten === '') {
                continue;
            }
            $key = mb_strtolower($ten);
            if (! isset($merged[$key])) {
                $merged[$key] = ['ten' => $ten, 'count' => 0];
            }
            $merged[$key]['count'] += (int) $row->company_count;
        }

        $catalogByName = $this->catalogNameIndex($config['cap'], $config['loai']);

        $groups = [];
        $newGroups = 0;
        $existingGroups = 0;
        $totalCompanies = 0;

        foreach ($merged as $item) {
            $lookup = mb_strtolower($item['ten']);
            $existingId = $catalogByName[$lookup] ?? null;
            $exists = $existingId !== null;
            if ($exists) {
                $existingGroups++;
            } else {
                $newGroups++;
            }
            $totalCompanies += $item['count'];
            $groups[] = [
                'ten' => $item['ten'],
                'count' => $item['count'],
                'existsInCatalog' => $exists,
                'existingId' => $existingId,
            ];
        }

        usort($groups, fn (array $a, array $b) => $b['count'] <=> $a['count']);

        $catalogLabels = [
            'tinh' => 'Bảng tỉnh',
            'quan_huyen' => 'Bảng quận huyện',
            'phuong_xa' => 'Bảng phường xã',
        ];

        return [
            'field' => $field,
            'label' => $config['label'],
            'catalog' => $catalogLabels[$config['cap']],
            'loai' => $config['loai'],
            'totalGroups' => count($groups),
            'newGroups' => $newGroups,
            'existingGroups' => $existingGroups,
            'totalCompanies' => $totalCompanies,
            'groups' => $groups,
        ];
    }

    /**
     * Lưu các giá trị text (group-by) đã chọn vào bảng danh mục tương ứng, rồi liên kết DN.
     *
     * @param  array<int, string>|null  $names  null = tất cả tên chưa có trong danh mục
     * @return array<string, mixed>
     */
    public function commitRawGroups(
        string $field,
        ?array $names = null,
        bool $linkCompanies = true,
        ?User $user = null,
    ): array {
        $config = self::FIELD_CONFIG[$field] ?? null;
        if ($config === null) {
            throw new \InvalidArgumentException('Field hành chính không được hỗ trợ.');
        }

        $preview = $this->previewRawGroups($field, $user);
        $selectedLookup = null;
        if ($names !== null) {
            $selectedLookup = [];
            foreach ($names as $name) {
                $normalized = HanhChinhCodeGenerator::normalizeName((string) $name);
                if ($normalized !== '') {
                    $selectedLookup[mb_strtolower($normalized)] = $normalized;
                }
            }
        }

        $this->loadCatalogs();
        $created = 0;
        $skippedExisting = 0;
        $resolveResult = ['createdTinh' => 0, 'createdQuanHuyen' => 0, 'createdPhuongXa' => 0];

        foreach ($preview['groups'] as $group) {
            $key = mb_strtolower($group['ten']);
            if ($selectedLookup !== null && ! isset($selectedLookup[$key])) {
                continue;
            }

            if ($group['existsInCatalog']) {
                $skippedExisting++;

                continue;
            }

            match ($config['cap']) {
                'tinh' => $this->resolveTinh($group['ten'], $config['loai'], false, $resolveResult),
                'quan_huyen' => $this->resolveQuanHuyen($group['ten'], $config['loai'], null, false, $resolveResult),
                default => $this->resolvePhuongXa($group['ten'], $config['loai'], null, false, $resolveResult),
            };
            $created++;
        }

        $linkResult = null;
        if ($linkCompanies) {
            $linkResult = $this->syncField($field, false, $user);
        }

        return [
            'field' => $field,
            'label' => $config['label'],
            'catalog' => $config['cap'],
            'loai' => $config['loai'],
            'created' => $created,
            'skippedExisting' => $skippedExisting,
            'createdTinh' => $resolveResult['createdTinh'],
            'createdQuanHuyen' => $resolveResult['createdQuanHuyen'],
            'createdPhuongXa' => $resolveResult['createdPhuongXa'],
            'link' => $linkResult,
        ];
    }

    /**
     * @return array<string, int> lowercase ten → id
     */
    private function catalogNameIndex(string $cap, string $loai): array
    {
        $index = [];

        $items = match ($cap) {
            'tinh' => HanhChinhTinh::query()->where('loai', $loai)->get(['id', 'ten']),
            'quan_huyen' => HanhChinhQuanHuyen::query()->where('loai', $loai)->get(['id', 'ten']),
            default => HanhChinhPhuongXa::query()->where('loai', $loai)->get(['id', 'ten']),
        };

        foreach ($items as $item) {
            $key = mb_strtolower(HanhChinhCodeGenerator::normalizeName((string) $item->ten));
            $index[$key] ??= $item->id;
        }

        return $index;
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
