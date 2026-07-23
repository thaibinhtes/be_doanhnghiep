<?php

namespace App\Http\Controllers\Api;

use App\Models\HanhChinhPhuongXa;
use App\Models\HanhChinhQuanHuyen;
use App\Models\HanhChinhTinh;
use App\Support\CatalogCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Danh mục hành chính hợp nhất (tỉnh / quận huyện / phường xã, loai cu|moi).
 */
class HanhChinhDanhMucController extends ApiController
{
    private const CATALOG_TTL = 3600;

    public function index(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'cap' => ['required', 'string', 'in:tinh,quan-huyen,phuong-xa'],
            'loai' => ['nullable', 'string', 'in:cu,moi'],
            'search' => ['nullable', 'string', 'max:255'],
            // Avoid exists:* here — that adds an extra DB round-trip on every request.
            'tinhId' => ['nullable', 'integer', 'min:1'],
            'quanHuyenId' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $cap = $payload['cap'];
        $loai = $payload['loai'] ?? null;
        $search = trim((string) ($payload['search'] ?? ''));
        $page = max((int) ($payload['page'] ?? $request->query('page', 1)), 1);
        $perPage = min(max((int) ($payload['perPage'] ?? $request->query('perPage', 50)), 1), 200);
        $tinhId = ! empty($payload['tinhId']) ? (int) $payload['tinhId'] : null;
        $quanHuyenId = ! empty($payload['quanHuyenId']) ? (int) $payload['quanHuyenId'] : null;

        // Browse/dropdown (không search): cache dài — danh mục ít đổi.
        if ($search === '') {
            $cacheKey = implode(':', [
                $cap,
                $loai ?: 'all',
                'tinh:'.($tinhId ?? 0),
                'qh:'.($quanHuyenId ?? 0),
                "p{$page}",
                "pp{$perPage}",
            ]);

            $cached = CatalogCache::remember(
                CatalogCache::BUCKET_HANH_CHINH,
                $cacheKey,
                self::CATALOG_TTL,
                fn () => $this->fetchCatalogPage($cap, $loai, $tinhId, $quanHuyenId, '', $page, $perPage),
            );

            return $this->listResponse(
                $cached['data'],
                $page,
                $perPage,
                $cached['total'],
            );
        }

        $result = $this->fetchCatalogPage($cap, $loai, $tinhId, $quanHuyenId, $search, $page, $perPage);

        return $this->listResponse($result['data'], $page, $perPage, $result['total']);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'cap' => ['required', 'string', 'in:tinh,quan-huyen,phuong-xa'],
            'loai' => ['required', 'string', 'in:cu,moi'],
            'ten' => ['required', 'string', 'max:255'],
            'ma' => ['nullable', 'string', 'max:50'],
            'parentId' => ['nullable', 'integer'],
        ]);

        $name = trim($payload['ten']);
        $parentId = $payload['parentId'] ?? null;
        $ma = isset($payload['ma']) ? trim((string) $payload['ma']) : null;
        $ma = $ma === '' ? null : $ma;

        $item = match ($payload['cap']) {
            'tinh' => $this->findOrCreateProvince($name, $payload['loai']),
            'quan-huyen' => $this->findOrCreateDistrict($name, $payload['loai'], $parentId),
            default => $this->findOrCreateWard($name, $payload['loai'], $parentId),
        };

        if ($ma !== null && $item->ma !== $ma) {
            $item->update(['ma' => $ma]);
            $item->refresh();
        }

        CatalogCache::bump(CatalogCache::BUCKET_HANH_CHINH);

        $item->loadMissing(array_filter([
            method_exists($item, 'tinh') ? 'tinh' : null,
            method_exists($item, 'quanHuyen') ? 'quanHuyen' : null,
        ]));

        return $this->success($this->mapRow($item), 'Đã lưu đơn vị hành chính', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate([
            'cap' => ['required', 'string', 'in:tinh,quan-huyen,phuong-xa'],
            'loai' => ['required', 'string', 'in:cu,moi'],
            'ten' => ['required', 'string', 'max:255'],
            'ma' => ['nullable', 'string', 'max:50'],
            'parentId' => ['nullable', 'integer', 'min:1'],
        ]);

        $name = trim($payload['ten']);
        $ma = isset($payload['ma']) ? trim((string) $payload['ma']) : null;
        $ma = $ma === '' ? null : $ma;
        $parentId = $payload['parentId'] ?? null;

        $item = match ($payload['cap']) {
            'tinh' => $this->updateProvince($id, $name, $payload['loai'], $ma),
            'quan-huyen' => $this->updateDistrict($id, $name, $payload['loai'], $ma, $parentId),
            default => $this->updateWard($id, $name, $payload['loai'], $ma, $parentId),
        };

        if ($item instanceof JsonResponse) {
            return $item;
        }

        CatalogCache::bump(CatalogCache::BUCKET_HANH_CHINH);

        $item->loadMissing(array_filter([
            method_exists($item, 'tinh') ? 'tinh' : null,
            method_exists($item, 'quanHuyen') ? 'quanHuyen' : null,
        ]));

        return $this->success($this->mapRow($item), 'Đã cập nhật đơn vị hành chính');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate([
            'cap' => ['required', 'string', 'in:tinh,quan-huyen,phuong-xa'],
        ]);

        $result = match ($payload['cap']) {
            'tinh' => $this->deleteProvince($id),
            'quan-huyen' => $this->deleteDistrict($id),
            default => $this->deleteWard($id),
        };

        if ($result instanceof JsonResponse) {
            return $result;
        }

        CatalogCache::bump(CatalogCache::BUCKET_HANH_CHINH);

        return $this->success(null, 'Đã xóa đơn vị hành chính');
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    private function fetchCatalogPage(
        string $cap,
        ?string $loai,
        ?int $tinhId,
        ?int $quanHuyenId,
        string $search,
        int $page,
        int $perPage,
    ): array {
        // Tỉnh list nhỏ — load hết rồi slice (tránh COUNT riêng).
        if ($cap === 'tinh' && $search === '' && $tinhId === null && $quanHuyenId === null) {
            $query = HanhChinhTinh::query()->select(['id', 'ten', 'loai', 'ma']);
            if ($loai) {
                $query->where('loai', $loai);
            }
            $all = $query->orderBy('ten')->limit(200)->get();
            $rows = $page > 1
                ? $all->slice(($page - 1) * $perPage, $perPage)->values()
                : $all->take($perPage)->values();

            return [
                'data' => $rows->map(fn ($item) => $this->mapRow($item))->all(),
                'total' => $all->count(),
            ];
        }

        $query = match ($cap) {
            'tinh' => HanhChinhTinh::query()->select(['id', 'ten', 'loai', 'ma']),
            'quan-huyen' => HanhChinhQuanHuyen::query()
                ->select(['id', 'ten', 'loai', 'ma', 'tinh_id'])
                ->with('tinh:id,ten,loai'),
            default => HanhChinhPhuongXa::query()
                ->select(['id', 'ten', 'loai', 'ma', 'tinh_id', 'quan_huyen_id'])
                ->with([
                    'quanHuyen:id,ten,loai',
                    'tinh:id,ten,loai',
                ]),
        };

        if ($loai) {
            $query->where('loai', $loai);
        }
        if ($cap === 'quan-huyen' && $tinhId !== null) {
            $query->where('tinh_id', $tinhId);
        }
        if ($cap === 'phuong-xa' && $quanHuyenId !== null) {
            $query->where('quan_huyen_id', $quanHuyenId);
        }
        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search) {
                $inner->where('ten', 'like', $search.'%')
                    ->orWhere('ten', 'like', '%'.$search.'%');
            });
        }

        $query->orderBy('ten');

        [$rows, $total] = $this->paginateWithoutWastefulCount($query, $page, $perPage);

        return [
            'data' => $rows->map(fn ($item) => $this->mapRow($item))->values()->all(),
            'total' => $total,
        ];
    }

    private function mapRow(mixed $item): array
    {
        $tinh = method_exists($item, 'tinh') ? $item->tinh : null;
        $quanHuyen = method_exists($item, 'quanHuyen') ? $item->quanHuyen : null;

        return [
            'id' => $item->id,
            'ten' => $item->ten,
            'loai' => $item->loai,
            'ma' => $item->ma,
            'tinh' => $tinh ? ['id' => $tinh->id, 'ten' => $tinh->ten] : null,
            'quanHuyen' => $quanHuyen ? ['id' => $quanHuyen->id, 'ten' => $quanHuyen->ten] : null,
        ];
    }

    /**
     * Skip COUNT(*) when page 1 already returns fewer rows than perPage (common for tỉnh / filtered dropdowns).
     *
     * @return array{0: \Illuminate\Support\Collection, 1: int}
     */
    private function paginateWithoutWastefulCount(Builder $query, int $page, int $perPage): array
    {
        $rows = (clone $query)
            ->forPage($page, $perPage)
            ->get();

        if ($page === 1 && $rows->count() < $perPage) {
            return [$rows, $rows->count()];
        }

        $total = (clone $query)->toBase()->getCountForPagination();

        return [$rows, $total];
    }

    /**
     * @param  array<int, array<string, mixed>>  $data
     */
    private function listResponse(array $data, int $page, int $perPage, int $total): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Lấy danh mục hành chính hợp nhất thành công',
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'last_page' => max((int) ceil($total / max($perPage, 1)), 1),
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    private function findOrCreateProvince(string $name, string $loai): HanhChinhTinh
    {
        return HanhChinhTinh::query()
            ->where('loai', $loai)
            ->whereRaw('LOWER(TRIM(ten)) = LOWER(TRIM(?))', [$name])
            ->first()
            ?? HanhChinhTinh::query()->create(['ten' => $name, 'loai' => $loai]);
    }

    private function findOrCreateDistrict(string $name, string $loai, ?int $parentId): HanhChinhQuanHuyen
    {
        if ($parentId !== null) {
            HanhChinhTinh::query()->whereKey($parentId)->where('loai', $loai)->firstOrFail();
        }

        return HanhChinhQuanHuyen::query()
            ->where('loai', $loai)
            ->where('tinh_id', $parentId)
            ->whereRaw('LOWER(TRIM(ten)) = LOWER(TRIM(?))', [$name])
            ->first()
            ?? HanhChinhQuanHuyen::query()->create([
                'ten' => $name,
                'loai' => $loai,
                'tinh_id' => $parentId,
            ]);
    }

    private function findOrCreateWard(string $name, string $loai, ?int $parentId): HanhChinhPhuongXa
    {
        $district = $parentId === null
            ? null
            : HanhChinhQuanHuyen::query()->whereKey($parentId)->where('loai', $loai)->firstOrFail();

        return HanhChinhPhuongXa::query()
            ->where('loai', $loai)
            ->where('quan_huyen_id', $parentId)
            ->whereRaw('LOWER(TRIM(ten)) = LOWER(TRIM(?))', [$name])
            ->first()
            ?? HanhChinhPhuongXa::query()->create([
                'ten' => $name,
                'loai' => $loai,
                'quan_huyen_id' => $parentId,
                'tinh_id' => $district?->tinh_id,
            ]);
    }

    private function updateProvince(int $id, string $name, string $loai, ?string $ma): HanhChinhTinh|JsonResponse
    {
        $item = HanhChinhTinh::query()->whereKey($id)->first();
        if ($item === null) {
            return $this->error('Không tìm thấy tỉnh/thành phố.', 404);
        }

        if ($this->provinceNameExists($name, $loai, $id)) {
            return $this->error('Tên tỉnh/thành phố đã tồn tại với loại này.', 422);
        }

        $item->update([
            'ten' => $name,
            'loai' => $loai,
            'ma' => $ma,
        ]);

        return $item->fresh();
    }

    private function updateDistrict(int $id, string $name, string $loai, ?string $ma, ?int $parentId): HanhChinhQuanHuyen|JsonResponse
    {
        $item = HanhChinhQuanHuyen::query()->whereKey($id)->first();
        if ($item === null) {
            return $this->error('Không tìm thấy quận/huyện.', 404);
        }

        if ($parentId !== null) {
            $province = HanhChinhTinh::query()->whereKey($parentId)->where('loai', $loai)->first();
            if ($province === null) {
                return $this->error('Tỉnh/thành phố cha không hợp lệ hoặc không cùng loại.', 422);
            }
        }

        if ($this->districtNameExists($name, $loai, $parentId, $id)) {
            return $this->error('Tên quận/huyện đã tồn tại trong tỉnh này.', 422);
        }

        $item->update([
            'ten' => $name,
            'loai' => $loai,
            'ma' => $ma,
            'tinh_id' => $parentId,
        ]);

        return $item->fresh();
    }

    private function updateWard(int $id, string $name, string $loai, ?string $ma, ?int $parentId): HanhChinhPhuongXa|JsonResponse
    {
        $item = HanhChinhPhuongXa::query()->whereKey($id)->first();
        if ($item === null) {
            return $this->error('Không tìm thấy phường/xã.', 404);
        }

        $district = null;
        if ($parentId !== null) {
            $district = HanhChinhQuanHuyen::query()->whereKey($parentId)->where('loai', $loai)->first();
            if ($district === null) {
                return $this->error('Quận/huyện cha không hợp lệ hoặc không cùng loại.', 422);
            }
        }

        if ($this->wardNameExists($name, $loai, $parentId, $id)) {
            return $this->error('Tên phường/xã đã tồn tại trong quận/huyện này.', 422);
        }

        $item->update([
            'ten' => $name,
            'loai' => $loai,
            'ma' => $ma,
            'quan_huyen_id' => $parentId,
            'tinh_id' => $district?->tinh_id,
        ]);

        return $item->fresh();
    }

    private function deleteProvince(int $id): true|JsonResponse
    {
        $item = HanhChinhTinh::query()->whereKey($id)->first();
        if ($item === null) {
            return $this->error('Không tìm thấy tỉnh/thành phố.', 404);
        }

        $childDistricts = HanhChinhQuanHuyen::query()->where('tinh_id', $id)->count();
        $childWards = HanhChinhPhuongXa::query()->where('tinh_id', $id)->count();
        if ($childDistricts > 0 || $childWards > 0) {
            return $this->error(
                "Không thể xóa: còn {$childDistricts} quận/huyện và {$childWards} phường/xã thuộc tỉnh này.",
                422,
            );
        }

        $this->detachCompanyLinks('tinh', (string) $item->loai, $id);
        $item->delete();

        return true;
    }

    private function deleteDistrict(int $id): true|JsonResponse
    {
        $item = HanhChinhQuanHuyen::query()->whereKey($id)->first();
        if ($item === null) {
            return $this->error('Không tìm thấy quận/huyện.', 404);
        }

        $childWards = HanhChinhPhuongXa::query()->where('quan_huyen_id', $id)->count();
        if ($childWards > 0) {
            return $this->error("Không thể xóa: còn {$childWards} phường/xã thuộc quận/huyện này.", 422);
        }

        $this->detachCompanyLinks('quan-huyen', (string) $item->loai, $id);
        $item->delete();

        return true;
    }

    private function deleteWard(int $id): true|JsonResponse
    {
        $item = HanhChinhPhuongXa::query()->whereKey($id)->first();
        if ($item === null) {
            return $this->error('Không tìm thấy phường/xã.', 404);
        }

        $this->detachCompanyLinks('phuong-xa', (string) $item->loai, $id);
        $item->delete();

        return true;
    }

    private function detachCompanyLinks(string $cap, string $loai, int $id): void
    {
        $column = match ([$cap, $loai]) {
            ['tinh', 'cu'] => 'tinh_thanh_cu_id',
            ['tinh', 'moi'] => 'tinh_thanh_moi_id',
            ['quan-huyen', 'cu'] => 'quan_huyen_cu_id',
            ['quan-huyen', 'moi'] => 'quan_huyen_moi_id',
            ['phuong-xa', 'cu'] => 'xa_phuong_cu_id',
            ['phuong-xa', 'moi'] => 'xa_phuong_moi_id',
            default => null,
        };

        if ($column === null || ! Schema::hasColumn('doanh_nghieps', $column)) {
            return;
        }

        DB::table('doanh_nghieps')
            ->where($column, $id)
            ->update([$column => null, 'updated_at' => now()]);
    }

    private function provinceNameExists(string $name, string $loai, ?int $excludeId = null): bool
    {
        $query = HanhChinhTinh::query()
            ->where('loai', $loai)
            ->whereRaw('LOWER(TRIM(ten)) = LOWER(TRIM(?))', [$name]);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    private function districtNameExists(string $name, string $loai, ?int $parentId, ?int $excludeId = null): bool
    {
        $query = HanhChinhQuanHuyen::query()
            ->where('loai', $loai)
            ->where('tinh_id', $parentId)
            ->whereRaw('LOWER(TRIM(ten)) = LOWER(TRIM(?))', [$name]);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    private function wardNameExists(string $name, string $loai, ?int $parentId, ?int $excludeId = null): bool
    {
        $query = HanhChinhPhuongXa::query()
            ->where('loai', $loai)
            ->where('quan_huyen_id', $parentId)
            ->whereRaw('LOWER(TRIM(ten)) = LOWER(TRIM(?))', [$name]);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
