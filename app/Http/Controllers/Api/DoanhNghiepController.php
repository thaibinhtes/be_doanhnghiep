<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreDoanhNghiepRequest;
use App\Http\Requests\Api\UpdateDoanhNghiepRequest;
use App\Http\Resources\DoanhNghiepResource;
use App\Models\DoanhNghiep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DoanhNghiepController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        $query = DoanhNghiep::query()
            ->when(request('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('ten_doanh_nghiep', 'like', "%{$search}%")
                        ->orWhere('ma_so_doanh_nghiep', 'like', "%{$search}%")
                        ->orWhere('dia_chi', 'like', "%{$search}%");
                });
            })
            ->when(request('quanHuyen'), function ($query, $quanHuyen) {
                $query->where('quan_huyen', $quanHuyen);
            })
            ->when(request('phuongXa'), function ($query, $phuongXa) {
                $query->where('phuong_xa', $phuongXa);
            })
            ->when(request('trangThai'), function ($query, $trangThai) {
                $query->where('trang_thai', $trangThai);
            })
            ->when(request('loaiHinhDN'), function ($query, $loaiHinhDN) {
                $query->where('loai_hinh_dn', $loaiHinhDN);
            })
            ->when(request('loaiDN'), function ($query, $loaiDN) {
                $query->where('loai_dn', $loaiDN);
            })
            ->when(request('sortBy'), function ($query, $sortBy) {
                $direction = request('sortDirection', 'asc');
                $allowedSorts = [
                    'tt', 'ma_so_doanh_nghiep', 'ten_doanh_nghiep',
                    'quan_huyen', 'phuong_xa', 'trang_thai',
                    'loai_hinh_dn', 'so_luong_lao_dong', 'created_at'
                ];
                if (in_array($sortBy, $allowedSorts)) {
                    $query->orderBy($sortBy, $direction);
                }
            }, function ($query) {
                $query->orderBy('created_at', 'desc');
            });

        $perPage = request('perPage', 15);
        $doanhNghieps = $query->paginate($perPage);

        return DoanhNghiepResource::collection($doanhNghieps);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDoanhNghiepRequest $request): JsonResponse
    {
        $data = $this->mapCamelToSnake($request->validated());
        $doanhNghiep = DoanhNghiep::create($data);
        $doanhNghiep->load(['chuSoHuu', 'nguoiDaiDien']);

        return $this->success(
            new DoanhNghiepResource($doanhNghiep),
            'Doanh nghiệp created successfully',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(DoanhNghiep $doanhNghiep): JsonResponse
    {
        $doanhNghiep->load(['chuSoHuu', 'nguoiDaiDien', 'members']);

        return $this->success(new DoanhNghiepResource($doanhNghiep));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDoanhNghiepRequest $request, DoanhNghiep $doanhNghiep): JsonResponse
    {
        $data = $this->mapCamelToSnake($request->validated());
        $doanhNghiep->update($data);
        $doanhNghiep->load(['chuSoHuu', 'nguoiDaiDien']);

        return $this->success(
            new DoanhNghiepResource($doanhNghiep->fresh()),
            'Doanh nghiệp updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DoanhNghiep $doanhNghiep): JsonResponse
    {
        $doanhNghiep->delete();

        return $this->success(null, 'Doanh nghiệp deleted successfully');
    }

    /**
     * Map camelCase keys to snake_case for database storage.
     */
    private function mapCamelToSnake(array $data): array
    {
        $mapping = [
            'maSoDoanhNghiep' => 'ma_so_doanh_nghiep',
            'tenDoanhNghiep' => 'ten_doanh_nghiep',
            'diaChi' => 'dia_chi',
            'quanHuyen' => 'quan_huyen',
            'phuongXa' => 'phuong_xa',
            'vonDieuLe' => 'von_dieu_le',
            'trangThai' => 'trang_thai',
            'dienThoai' => 'dien_thoai',
            'nguoiDaiDien' => 'nguoi_dai_dien',
            'chuSoHuu' => 'chu_so_huu',
            'nganhNgheKDChinh' => 'nganh_nghe_kd_chinh',
            'nganhNgheKD' => 'nganh_nghe_kd',
            'ngayCap' => 'ngay_cap',
            'ngayDangKyThayDoi' => 'ngay_dang_ky_thay_doi',
            'loaiHinhDN' => 'loai_hinh_dn',
            'soLuongLaoDong' => 'so_luong_lao_dong',
            'dsThanhVienGopVon' => 'ds_thanh_vien_gop_von',
            'dsCoDong' => 'ds_co_dong',
            'loaiDN' => 'loai_dn',
        ];

        $result = [];
        foreach ($data as $key => $value) {
            $result[$mapping[$key] ?? $key] = $value;
        }

        return $result;
    }
}
