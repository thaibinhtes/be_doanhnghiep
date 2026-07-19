<?php

namespace App\Http\Controllers\Api;

use App\Models\HanhChinhPhuongXa;
use App\Models\HanhChinhQuanHuyen;
use App\Models\HanhChinhTinh;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Danh mục hành chính hợp nhất (tỉnh / quận huyện / phường xã, loai cu|moi).
 */
class HanhChinhDanhMucController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'cap' => ['required', 'string', 'in:tinh,quan-huyen,phuong-xa'],
            'loai' => ['nullable', 'string', 'in:cu,moi'],
            'search' => ['nullable', 'string', 'max:255'],
            'tinhId' => ['nullable', 'integer', 'exists:hanh_chinh_tinh,id'],
            'quanHuyenId' => ['nullable', 'integer', 'exists:hanh_chinh_quan_huyen,id'],
        ]);

        $query = match ($payload['cap']) {
            'tinh' => HanhChinhTinh::query(),
            'quan-huyen' => HanhChinhQuanHuyen::query()->with('tinh:id,ten,loai'),
            default => HanhChinhPhuongXa::query()->with([
                'quanHuyen:id,ten,loai',
                'tinh:id,ten,loai',
            ]),
        };

        if (! empty($payload['loai'])) {
            $query->where('loai', $payload['loai']);
        }
        if ($payload['cap'] === 'quan-huyen' && ! empty($payload['tinhId'])) {
            $query->where('tinh_id', $payload['tinhId']);
        }
        if ($payload['cap'] === 'phuong-xa' && ! empty($payload['quanHuyenId'])) {
            $query->where('quan_huyen_id', $payload['quanHuyenId']);
        }

        $search = trim((string) ($payload['search'] ?? ''));
        if ($search !== '') {
            $query->where('ten', 'like', "%{$search}%");
        }

        $perPage = min(max((int) $request->query('perPage', 50), 1), 200);
        $items = $query->orderBy('loai')->orderBy('ten')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh mục hành chính hợp nhất thành công',
            'data' => collect($items->items())->map(fn ($item) => [
                'id' => $item->id,
                'ten' => $item->ten,
                'loai' => $item->loai,
                'ma' => $item->ma,
                'tinh' => $item->tinh ? ['id' => $item->tinh->id, 'ten' => $item->tinh->ten] : null,
                'quanHuyen' => $item->quanHuyen ? ['id' => $item->quanHuyen->id, 'ten' => $item->quanHuyen->ten] : null,
            ])->values(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'cap' => ['required', 'string', 'in:tinh,quan-huyen,phuong-xa'],
            'loai' => ['required', 'string', 'in:cu,moi'],
            'ten' => ['required', 'string', 'max:255'],
            'parentId' => ['nullable', 'integer'],
        ]);

        $name = trim($payload['ten']);
        $parentId = $payload['parentId'] ?? null;

        $item = match ($payload['cap']) {
            'tinh' => $this->findOrCreateProvince($name, $payload['loai']),
            'quan-huyen' => $this->findOrCreateDistrict($name, $payload['loai'], $parentId),
            default => $this->findOrCreateWard($name, $payload['loai'], $parentId),
        };

        $item->loadMissing(array_filter([
            method_exists($item, 'tinh') ? 'tinh' : null,
            method_exists($item, 'quanHuyen') ? 'quanHuyen' : null,
        ]));

        return $this->success([
            'id' => $item->id,
            'ten' => $item->ten,
            'loai' => $item->loai,
            'ma' => $item->ma,
            'tinh' => $item->tinh ? ['id' => $item->tinh->id, 'ten' => $item->tinh->ten] : null,
            'quanHuyen' => $item->quanHuyen
                ? ['id' => $item->quanHuyen->id, 'ten' => $item->quanHuyen->ten]
                : null,
        ], 'Đã lưu đơn vị hành chính', 201);
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
}
