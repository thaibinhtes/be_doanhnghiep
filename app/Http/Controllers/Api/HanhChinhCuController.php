<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\QuanHuyenCuResource;
use App\Http\Resources\TinhThanhCuResource;
use App\Http\Resources\XaPhuongCuResource;
use App\Models\QuanHuyenCu;
use App\Models\TinhThanhCu;
use App\Models\XaPhuongCu;
use App\Support\HanhChinhSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HanhChinhCuController extends ApiController
{
    public function __construct(private readonly HanhChinhSyncService $syncService)
    {
    }

    public function indexProvinces(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $query = TinhThanhCu::query()->orderBy('full_name');

        if ($search !== '') {
            $query->where('full_name', 'like', "%{$search}%");
        }

        return $this->success(TinhThanhCuResource::collection($query->get()));
    }

    public function indexDistricts(string $provinceCode, Request $request): JsonResponse
    {
        $province = TinhThanhCu::query()->find($provinceCode);
        if (!$province) {
            return $this->error('Tỉnh/thành (cũ) không tồn tại.', 404);
        }

        $search = trim((string) $request->query('search', ''));

        $query = $province->quanHuyen()->orderBy('full_name');

        if ($search !== '') {
            $query->where('full_name', 'like', "%{$search}%");
        }

        return $this->success(QuanHuyenCuResource::collection($query->get()));
    }

    public function indexWards(string $districtCode, Request $request): JsonResponse
    {
        $district = QuanHuyenCu::query()->with('tinhThanh')->find($districtCode);
        if (!$district) {
            return $this->error('Quận/huyện (cũ) không tồn tại.', 404);
        }

        $search = trim((string) $request->query('search', ''));

        $query = $district->xaPhuong()->with('mapping.xaPhuongMoi')->orderBy('full_name');

        if ($search !== '') {
            $query->where('full_name', 'like', "%{$search}%");
        }

        return $this->success(XaPhuongCuResource::collection($query->get()));
    }

    public function bulkImport(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.tinhThanhCu' => ['nullable', 'string'],
            'items.*.tinh_thanh_cu' => ['nullable', 'string'],
            'items.*.quanHuyenCu' => ['nullable', 'string'],
            'items.*.quan_huyen_cu' => ['nullable', 'string'],
            'items.*.xaPhuongCu' => ['nullable', 'string'],
            'items.*.xa_phuong_cu' => ['nullable', 'string'],
            'items.*.loaiCu' => ['nullable', 'string'],
            'items.*.loai_cu' => ['nullable', 'string'],
            'items.*.xaPhuongMoi' => ['nullable', 'string'],
            'items.*.xa_phuong_moi' => ['nullable', 'string'],
            'items.*.loaiMoi' => ['nullable', 'string'],
            'items.*.loai_moi' => ['nullable', 'string'],
            'items.*.tinhThanhMoiCode' => ['nullable', 'string'],
            'items.*.tinh_thanh_moi_code' => ['nullable', 'string'],
            'items.*.groupNo' => ['nullable', 'integer'],
            'items.*.group_no' => ['nullable', 'integer'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $counts = $this->syncService->importLegacyWithMappings($payload['items']);

        return $this->success($counts, 'Import dữ liệu hành chính cũ và mapping thành công');
    }
}
