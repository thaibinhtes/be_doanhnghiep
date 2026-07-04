<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreDnLoaiHinhRequest;
use App\Http\Requests\Api\UpdateDnLoaiHinhRequest;
use App\Http\Resources\DnLoaiHinhResource;
use App\Models\DnLoaiHinh;
use Illuminate\Http\JsonResponse;

class DnLoaiHinhController extends ApiController
{
    public function index(): JsonResponse
    {
        $types = DnLoaiHinh::query()
            ->withCount('doanhNghieps')
            ->when(request()->has('isActive'), function ($q) {
                $isActive = filter_var(request('isActive'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($isActive !== null) {
                    $q->where('is_active', $isActive);
                }
            })
            ->orderBy('thu_tu')
            ->orderBy('ten')
            ->get();

        return $this->success(DnLoaiHinhResource::collection($types));
    }

    public function store(StoreDnLoaiHinhRequest $request): JsonResponse
    {
        $data = $this->mapToModel($request->validated());

        if (!empty($data['mac_dinh'])) {
            DnLoaiHinh::query()->update(['mac_dinh' => false]);
        }

        $type = DnLoaiHinh::create($data);

        return $this->success(new DnLoaiHinhResource($type), 'Tạo loại hình doanh nghiệp thành công', 201);
    }

    public function show(DnLoaiHinh $dnLoaiHinh): JsonResponse
    {
        $dnLoaiHinh->loadCount('doanhNghieps');

        return $this->success(new DnLoaiHinhResource($dnLoaiHinh));
    }

    public function update(UpdateDnLoaiHinhRequest $request, DnLoaiHinh $dnLoaiHinh): JsonResponse
    {
        $data = $this->mapToModel($request->validated());

        if (!empty($data['mac_dinh'])) {
            DnLoaiHinh::query()->where('id', '!=', $dnLoaiHinh->id)->update(['mac_dinh' => false]);
        }

        $dnLoaiHinh->update($data);

        if (array_key_exists('ten', $data)) {
            $dnLoaiHinh->doanhNghieps()->update(['loai_hinh_dn' => $dnLoaiHinh->ten]);
        }

        return $this->success(new DnLoaiHinhResource($dnLoaiHinh->fresh()->loadCount('doanhNghieps')), 'Cập nhật loại hình thành công');
    }

    public function destroy(DnLoaiHinh $dnLoaiHinh): JsonResponse
    {
        if ($dnLoaiHinh->doanhNghieps()->exists()) {
            return $this->error('Không thể xóa loại hình đang được sử dụng bởi doanh nghiệp.', 422);
        }

        $dnLoaiHinh->delete();

        return $this->success(null, 'Xóa loại hình thành công');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapToModel(array $data): array
    {
        $map = [
            'thuTu' => 'thu_tu',
            'macDinh' => 'mac_dinh',
            'isActive' => 'is_active',
            'moTa' => 'mo_ta',
        ];

        $result = [];
        foreach ($data as $key => $value) {
            $result[$map[$key] ?? $key] = $value;
        }

        return $result;
    }
}
