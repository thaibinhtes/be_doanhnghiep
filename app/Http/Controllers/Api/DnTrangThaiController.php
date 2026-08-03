<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreDnTrangThaiRequest;
use App\Http\Requests\Api\UpdateDnTrangThaiRequest;
use App\Http\Resources\DnTrangThaiResource;
use App\Models\DnTrangThai;
use Illuminate\Http\JsonResponse;

class DnTrangThaiController extends ApiController
{
    public function index(): JsonResponse
    {
        $statuses = DnTrangThai::query()
            ->select(['id', 'ma', 'ten', 'loai', 'is_active', 'hien_thi_bao_cao', 'thu_tu_bao_cao', 'created_at', 'updated_at'])
            ->withCount('doanhNghieps')
            ->when(request('loai'), fn ($q, $loai) => $q->where('loai', $loai))
            ->when(request()->has('isActive'), function ($q) {
                $isActive = filter_var(request('isActive'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($isActive !== null) {
                    $q->where('is_active', $isActive);
                }
            })
            ->when(request()->has('hienThiBaoCao'), function ($q) {
                $show = filter_var(request('hienThiBaoCao'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($show !== null) {
                    $q->where('hien_thi_bao_cao', $show);
                }
            })
            ->orderBy('thu_tu_bao_cao')
            ->orderBy('ten')
            ->get();

        return $this->success(DnTrangThaiResource::collection($statuses));
    }

    public function store(StoreDnTrangThaiRequest $request): JsonResponse
    {
        $data = $this->mapToModel($request->validated());

        if (!empty($data['mac_dinh'])) {
            DnTrangThai::query()->update(['mac_dinh' => false]);
        }

        $status = DnTrangThai::create($data);

        return $this->success(new DnTrangThaiResource($status), 'Tạo trạng thái thành công', 201);
    }

    public function show(DnTrangThai $dnTrangThai): JsonResponse
    {
        $dnTrangThai->loadCount('doanhNghieps');

        return $this->success(new DnTrangThaiResource($dnTrangThai));
    }

    public function update(UpdateDnTrangThaiRequest $request, DnTrangThai $dnTrangThai): JsonResponse
    {
        $data = $this->mapToModel($request->validated());

        if (!empty($data['mac_dinh'])) {
            DnTrangThai::query()->where('id', '!=', $dnTrangThai->id)->update(['mac_dinh' => false]);
        }

        $dnTrangThai->update($data);

        return $this->success(new DnTrangThaiResource($dnTrangThai->fresh()), 'Cập nhật trạng thái thành công');
    }

    public function destroy(DnTrangThai $dnTrangThai): JsonResponse
    {
        if ($dnTrangThai->doanhNghieps()->exists()) {
            return $this->error('Không thể xóa trạng thái đang được sử dụng bởi doanh nghiệp.', 422);
        }

        $dnTrangThai->delete();

        return $this->success(null, 'Xóa trạng thái thành công');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapToModel(array $data): array
    {
        $map = [
            'yeuCauLyDo' => 'yeu_cau_ly_do',
            'hienThiBaoCao' => 'hien_thi_bao_cao',
            'thuTuBaoCao' => 'thu_tu_bao_cao',
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
