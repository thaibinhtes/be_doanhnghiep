<?php

namespace App\Http\Controllers\Api;

use App\Exports\DoanhNghiepExport;
use App\Exports\DoanhNghiepDinhDanhTemplateExport;
use App\Exports\DoanhNghiepTemplateExport;
use App\Http\Requests\Api\StoreDoanhNghiepRequest;
use App\Http\Requests\Api\UpdateDoanhNghiepRequest;
use App\Http\Resources\DoanhNghiepResource;
use App\Imports\DoanhNghiepDinhDanhImport;
use App\Imports\DoanhNghiepImport;
use App\Models\DoanhNghiep;
use App\Models\Member;
use App\Support\DoanhNghiepStatusHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DoanhNghiepController extends ApiController
{
    private const DINH_DANH_LABEL_UPDATED = 'Đã cập nhật định danh';
    private const DINH_DANH_LABEL_PENDING = 'Chưa cập nhật định danh';

    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        $perPage = request('perPage', 15);
        $doanhNghieps = $this->buildFilteredQuery()->paginate($perPage);

        return DoanhNghiepResource::collection($doanhNghieps);
    }

    /**
     * Export filtered companies to Excel.
     */
    public function export(): BinaryFileResponse
    {
        $filename = 'doanh-nghiep_' . now()->format('Y-m-d_His') . '.xlsx';

        $query = $this->buildFilteredQuery();
        if (!request('sortBy')) {
            $query->reorder()->orderByRaw('tt IS NULL')->orderBy('tt')->orderBy('id');
        }

        return Excel::download(
            new DoanhNghiepExport($query),
            $filename
        );
    }

    /**
     * Download empty Excel template for import.
     */
    public function exportTemplate(): BinaryFileResponse
    {
        return Excel::download(
            new DoanhNghiepTemplateExport(),
            'mau-import-doanh-nghiep.xlsx'
        );
    }

    /**
     * Download identity import template.
     */
    public function exportIdentityTemplate(): BinaryFileResponse
    {
        return Excel::download(
            new DoanhNghiepDinhDanhTemplateExport(),
            'mau-import-dinh-danh-doanh-nghiep.xlsx'
        );
    }

    /**
     * Import companies from Excel file.
     */
    public function import(): JsonResponse
    {
        request()->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $import = new DoanhNghiepImport();
        Excel::import($import, request()->file('file'));

        $result = $import->getResult();
        $total = $result['imported'] + $result['updated'];

        return $this->success(
            $result,
            "Import hoàn tất: {$result['imported']} mới, {$result['updated']} cập nhật, {$result['failed']} lỗi.",
            $total > 0 ? 200 : 422
        );
    }

    /**
     * Import identity status updates from Excel file.
     */
    public function importDinhDanh(): JsonResponse
    {
        request()->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $import = new DoanhNghiepDinhDanhImport();
        Excel::import($import, request()->file('file'));

        $result = $import->getResult();
        $total = $result['updated'];
        $statusCode = $total > 0 || $result['failed'] === 0 ? 200 : 422;

        return $this->success(
            $result,
            "Import định danh hoàn tất: {$result['updated']} cập nhật, {$result['failed']} lỗi.",
            $statusCode
        );
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
        $data = DoanhNghiepStatusHelper::applyStatus($data);
        $doanhNghiep = DoanhNghiep::create($data);

        if (!empty($danhSachTV)) {
            $this->syncMembersToCompany($doanhNghiep, $danhSachTV);
        }

        $doanhNghiep->load(['chuSoHuu', 'nguoiDaiDien', 'memberCompanies.member', 'dnTrangThai']);

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
        $doanhNghiep->load(['chuSoHuu', 'nguoiDaiDien', 'memberCompanies.member', 'dnTrangThai']);

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
        $data = DoanhNghiepStatusHelper::applyStatus($data, $doanhNghiep);

        if (!empty($data)) {
            $doanhNghiep->update($data);
            $doanhNghiep->save();
        }

        if ($danhSachTV !== null) {
            $doanhNghiep->memberCompanies()->delete();
            $this->syncMembersToCompany($doanhNghiep, $danhSachTV);
        }

        $doanhNghiep->load(['chuSoHuu', 'nguoiDaiDien', 'memberCompanies.member', 'dnTrangThai']);

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

        DoanhNghiepStatusHelper::syncDinhDanhStatus($doanhNghiep, $validated['daCapNhatDinhDanh']);
        $doanhNghiep->load(['chuSoHuu', 'nguoiDaiDien', 'memberCompanies.member', 'dnTrangThai']);

        return $this->success(
            new DoanhNghiepResource($doanhNghiep->fresh()),
            $validated['daCapNhatDinhDanh'] ? self::DINH_DANH_LABEL_UPDATED : self::DINH_DANH_LABEL_PENDING
        );
    }

    /**
     * Bulk update identity verification status by company business codes.
     */
    public function bulkUpdateDinhDanh(): JsonResponse
    {
        $validated = request()->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.maSoDoanhNghiep' => ['required', 'string', 'max:50'],
            'items.*.daCapNhatDinhDanh' => ['required', 'boolean'],
        ]);

        $updated = 0;
        $failed = 0;
        $errors = [];

        foreach ($validated['items'] as $index => $item) {
            $msdn = trim((string) $item['maSoDoanhNghiep']);
            $company = DoanhNghiep::query()->where('ma_so_doanh_nghiep', $msdn)->first();

            if (!$company) {
                $failed++;
                $errors[] = [
                    'row' => $index + 1,
                    'message' => "Không tìm thấy doanh nghiệp với MSDN {$msdn}.",
                ];
                continue;
            }

            DoanhNghiepStatusHelper::syncDinhDanhStatus($company, (bool) $item['daCapNhatDinhDanh']);
            $updated++;
        }

        return $this->success(
            [
                'imported' => 0,
                'updated' => $updated,
                'failed' => $failed,
                'errors' => $errors,
            ],
            "Cập nhật định danh hàng loạt: {$updated} thành công, {$failed} lỗi."
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
     * Build filtered query shared by index and export.
     */
    private function buildFilteredQuery(): Builder
    {
        return DoanhNghiep::query()
            ->with(['nguoiDaiDien', 'chuSoHuu', 'memberCompanies.member', 'dnTrangThai'])
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
            ->when(request('dnTrangThaiId'), function ($query, $dnTrangThaiId) {
                $query->where('dn_trang_thai_id', $dnTrangThaiId);
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
                    'loai_hinh_dn', 'so_luong_lao_dong', 'created_at',
                ];
                if (in_array($sortBy, $allowedSorts)) {
                    $query->orderBy($sortBy, $direction);
                }
            }, function ($query) {
                $query->orderBy('created_at', 'desc');
            });
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
            'dnTrangThaiId' => 'dn_trang_thai_id',
            'lyDoTrangThai' => 'ly_do_trang_thai',
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
