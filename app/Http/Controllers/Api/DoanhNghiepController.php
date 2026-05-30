<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreDoanhNghiepRequest;
use App\Http\Requests\Api\UpdateDoanhNghiepRequest;
use App\Http\Resources\DoanhNghiepResource;
use App\Models\DoanhNghiep;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DoanhNghiepController extends ApiController
{
    private const DINH_DANH_LABEL_UPDATED = 'Đã cập nhật định danh';
    private const DINH_DANH_LABEL_PENDING = 'Chưa cập nhật định danh';

    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        $query = DoanhNghiep::query()
            ->with(['nguoiDaiDien', 'chuSoHuu', 'memberCompanies.member'])
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
            ->when(request()->has('daCapNhatDinhDanh'), function ($query) {
                $daCapNhatDinhDanh = filter_var(request('daCapNhatDinhDanh'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($daCapNhatDinhDanh !== null) {
                    $query->where('da_cap_nhat_dinh_danh', $daCapNhatDinhDanh);
                }
            })
            ->when(request()->has('hasCoordinates'), function ($query) {
                $hasCoordinates = filter_var(request('hasCoordinates'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($hasCoordinates === true) {
                    $query->whereNotNull('long')->whereNotNull('lat');
                } elseif ($hasCoordinates === false) {
                    $query->where(function ($q) {
                        $q->whereNull('long')->orWhereNull('lat');
                    });
                }
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
        $validated = $request->validated();
        $danhSachTV = $validated['danhSachThanhVienGopVon'] ?? [];
        unset($validated['danhSachThanhVienGopVon']);

        $data = $this->mapCamelToSnake($validated);
        $doanhNghiep = DoanhNghiep::create($data);

        if (!empty($danhSachTV)) {
            $this->syncMembersToCompany($doanhNghiep, $danhSachTV);
        }

        $doanhNghiep->load(['chuSoHuu', 'nguoiDaiDien', 'memberCompanies.member']);

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
        $doanhNghiep->load(['chuSoHuu', 'nguoiDaiDien', 'memberCompanies.member']);

        return $this->success(new DoanhNghiepResource($doanhNghiep));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDoanhNghiepRequest $request, $id): JsonResponse
    {
        $validated = $request->validated();
        $danhSachTV = $validated['danhSachThanhVienGopVon'] ?? null;
        unset($validated['danhSachThanhVienGopVon']);
        $doanhNghiep = DoanhNghiep::find($id);

        if (!$doanhNghiep) {
            return $this->error("Not found!");
        }

        $data = $this->mapCamelToSnake($validated);

        if (!empty($data)) {
            $doanhNghiep->update($data);
            $doanhNghiep->save();
        }

        if ($danhSachTV !== null) {
            $doanhNghiep->memberCompanies()->delete();
            $this->syncMembersToCompany($doanhNghiep, $danhSachTV);
        }

        $doanhNghiep->load(['chuSoHuu', 'nguoiDaiDien', 'memberCompanies.member']);

        return $this->success(
            new DoanhNghiepResource($doanhNghiep->fresh()),
            'Doanh nghiệp updated successfully!!'
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
     * Update identity verification status of a company.
     */
    public function updateDinhDanh(DoanhNghiep $doanhNghiep): JsonResponse
    {
        $validated = request()->validate([
            'daCapNhatDinhDanh' => ['required', 'boolean'],
        ]);

        $doanhNghiep->update([
            'da_cap_nhat_dinh_danh' => $validated['daCapNhatDinhDanh'],
        ]);
        $doanhNghiep->load(['chuSoHuu', 'nguoiDaiDien', 'memberCompanies.member']);

        return $this->success(
            new DoanhNghiepResource($doanhNghiep->fresh()),
            $validated['daCapNhatDinhDanh'] ? self::DINH_DANH_LABEL_UPDATED : self::DINH_DANH_LABEL_PENDING
        );
    }

    /**
     * Sync members to company with pivot data from text input.
     */
    private function syncMembersToCompany(DoanhNghiep $doanhNghiep, array $danhSachTV): void
    {
        foreach ($danhSachTV as $item) {
            if (!is_array($item)) {
                continue;
            }

            $fullName = trim($item['fullName'] ?? '');
            if ($fullName === '') {
                continue;
            }

            $memberId = $item['memberId'] ?? null;

            if (!$memberId) {
                $member = Member::firstOrCreate(
                    ['full_name' => $fullName],
                    ['cccd' => null, 'status' => true]
                );
                $memberId = $member->id;
            }

            $doanhNghiep->members()->attach($memberId, [
                'date_join' => $item['dateJoin'] ?? null,
                'position' => $item['position'] ?? null,
                'investment_amount' => $item['investmentAmount'] ?? null,
            ]);
        }
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
            'long' => 'long',
            'lat' => 'lat',
            'quanHuyen' => 'quan_huyen',
            'phuongXa' => 'phuong_xa',
            'vonDieuLe' => 'von_dieu_le',
            'trangThai' => 'trang_thai',
            'daCapNhatDinhDanh' => 'da_cap_nhat_dinh_danh',
            'dienThoai' => 'dien_thoai',
            'nguoiDaiDienTen' => 'nguoi_dai_dien_ten',
            'ngaySinhNguoiDaiDien' => 'ngay_sinh_nguoi_dai_dien',
            'chuSoHuuTen' => 'chu_so_huu_ten',
            'nguoiDaiDienID' => 'nguoi_dai_dien_id',
            'chuSoHuuID' => 'chu_so_huu_id',
            'nganhNgheKDChinh' => 'nganh_nghe_kd_chinh',
            'nganhNgheKD' => 'nganh_nghe_kd',
            'ngayCap' => 'ngay_cap',
            'ngayDangKyThayDoi' => 'ngay_dang_ky_thay_doi',
            'loaiHinhDN' => 'loai_hinh_dn',
            'soLuongLaoDong' => 'so_luong_lao_dong',
            'loaiDN' => 'loai_dn',
            'dsCoDong' => 'ds_co_dong',
        ];

        $result = [];
        foreach ($data as $key => $value) {
            $result[$mapping[$key] ?? $key] = $value;
        }

        return $result;
    }
}
