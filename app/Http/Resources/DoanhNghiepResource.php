<?php

namespace App\Http\Resources;

use App\Models\DanhMucNganhNghe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;

class DoanhNghiepResource extends JsonResource
{
    /** @var Collection<string, DanhMucNganhNghe>|null */
    private static ?Collection $sharedNganhNgheCatalog = null;

    /**
     * Preload ngành nghề for the whole page (avoids N+1 in toArray).
     *
     * @param  mixed  $resource
     */
    public static function collection($resource): AnonymousResourceCollection
    {
        self::preloadNganhNgheCatalog($resource);

        return parent::collection($resource);
    }

    /**
     * @param  mixed  $resource
     */
    public static function preloadNganhNgheCatalog($resource): void
    {
        $items = self::unwrapResourceItems($resource);
        $codes = [];

        foreach ($items as $item) {
            $model = $item instanceof self ? $item->resource : $item;
            if (! is_object($model)) {
                continue;
            }

            if (! empty($model->nganh_nghe_kd_chinh)) {
                $codes[] = (string) $model->nganh_nghe_kd_chinh;
            }

            foreach ($model->nganh_nghe_kd ?? [] as $code) {
                if (is_string($code) && $code !== '') {
                    $codes[] = $code;
                }
            }
        }

        $codes = array_values(array_unique($codes));
        if ($codes === []) {
            self::$sharedNganhNgheCatalog = collect();

            return;
        }

        self::$sharedNganhNgheCatalog = DanhMucNganhNghe::query()
            ->whereIn('ma', $codes)
            ->get(['id', 'ma', 'ten', 'cap'])
            ->keyBy('ma');
    }

    public static function clearNganhNgheCatalog(): void
    {
        self::$sharedNganhNgheCatalog = null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $nganhNgheCatalog = $this->resolveNganhNgheCatalog();
        $nganhNgheKdCodes = $this->nganh_nghe_kd ?? [];

        return [
            'id' => $this->id,
            'tt' => $this->tt,
            'maSoDoanhNghiep' => $this->ma_so_doanh_nghiep,
            'tenDoanhNghiep' => $this->ten_doanh_nghiep,
            'diaChi' => $this->dia_chi,
            'diaChiCu' => $this->dia_chi_cu,
            'diaChiMoi' => $this->dia_chi_moi,
            'long' => $this->long !== null ? (float) $this->long : null,
            'lat' => $this->lat !== null ? (float) $this->lat : null,
            'quanHuyen' => $this->quan_huyen,
            'phuongXa' => $this->phuong_xa,
            'tinhThanhCu' => $this->tinh_thanh_cu
                ?? ($this->relationLoaded('tinhThanhCu') ? $this->tinhThanhCu?->full_name : null),
            'tinhThanhCuCode' => $this->tinh_thanh_cu_code,
            'tinhThanhMoi' => $this->tinh_thanh_moi,
            'tinhThanhCuLinked' => $this->whenLoaded('tinhThanhCu', fn () => $this->tinhThanhCu ? [
                'code' => $this->tinhThanhCu->code,
                'fullName' => $this->tinhThanhCu->full_name,
            ] : null),
            'quanHuyenCu' => $this->quan_huyen_cu
                ?? ($this->relationLoaded('quanHuyenCu') ? $this->quanHuyenCu?->full_name : null),
            'quanHuyenCuCode' => $this->quan_huyen_cu_code,
            'quanHuyenCuLinked' => $this->whenLoaded('quanHuyenCu', fn () => $this->quanHuyenCu ? [
                'code' => $this->quanHuyenCu->code,
                'fullName' => $this->quanHuyenCu->full_name,
            ] : null),
            'quanHuyenMoi' => $this->quan_huyen_moi
                ?? ($this->relationLoaded('tinhThanh') ? $this->tinhThanh?->full_name : null),
            'tinhThanhCode' => $this->tinh_thanh_code,
            'tinhThanh' => $this->whenLoaded('tinhThanh', fn () => [
                'code' => $this->tinhThanh?->code,
                'fullName' => $this->tinhThanh?->full_name,
            ]),
            'xaPhuongCu' => $this->xa_phuong_cu
                ?? ($this->relationLoaded('xaPhuongCu') ? $this->xaPhuongCu?->full_name : null),
            'phuongXaCu' => $this->xa_phuong_cu
                ?? ($this->relationLoaded('xaPhuongCu') ? $this->xaPhuongCu?->full_name : null),
            'xaPhuongCuCode' => $this->xa_phuong_cu_code,
            'xaPhuongCuLinked' => $this->whenLoaded('xaPhuongCu', fn () => $this->xaPhuongCu ? [
                'code' => $this->xaPhuongCu->code,
                'fullName' => $this->xaPhuongCu->full_name,
            ] : null),
            'xaPhuongMoi' => $this->xa_phuong_moi
                ?? ($this->relationLoaded('xaPhuong') ? $this->xaPhuong?->full_name : null),
            'phuongXaMoi' => $this->xa_phuong_moi
                ?? ($this->relationLoaded('xaPhuong') ? $this->xaPhuong?->full_name : null),
            'xaPhuongCode' => $this->xa_phuong_code,
            'xaPhuong' => $this->whenLoaded('xaPhuong', fn () => [
                'code' => $this->xaPhuong?->code,
                'fullName' => $this->xaPhuong?->full_name,
            ]),
            'tinhThanhCuId' => $this->tinh_thanh_cu_id,
            'tinhThanhMoiId' => $this->tinh_thanh_moi_id,
            'quanHuyenCuId' => $this->quan_huyen_cu_id,
            'xaPhuongCuId' => $this->xa_phuong_cu_id,
            'quanHuyenMoiId' => $this->quan_huyen_moi_id,
            'xaPhuongMoiId' => $this->xa_phuong_moi_id,
            'ghiChuHanhChinh' => $this->ghi_chu_hanh_chinh,
            'quanHuyenHanhChinhLinked' => $this->tinh_thanh_cu_code !== null
                || $this->quan_huyen_cu_code !== null
                || $this->tinh_thanh_code !== null,
            'quanHuyenCuMoiLabel' => $this->resolveQuanHuyenCuMoiLabel(),
            'vonDieuLe' => $this->von_dieu_le,
            'trangThai' => $this->trang_thai,
            'dnTrangThaiId' => $this->dn_trang_thai_id,
            'lyDoTrangThai' => $this->ly_do_trang_thai,
            'dnTrangThai' => $this->whenLoaded('dnTrangThai', fn () => new DnTrangThaiResource($this->dnTrangThai)),
            'tinhTrangThue' => $this->when(
                $this->relationLoaded('taxManagement'),
                fn () => $this->taxManagement !== null ? 'Đang hoạt động' : 'Không hoạt động',
            ),
            'hasTaxLink' => $this->when(
                $this->relationLoaded('taxManagement'),
                fn () => $this->taxManagement !== null,
            ),
            'daCapNhatDinhDanh' => (bool) $this->da_cap_nhat_dinh_danh,
            'trangThaiDinhDanh' => $this->da_cap_nhat_dinh_danh ? 'Đã cập nhật định danh' : 'Chưa cập nhật định danh',
            'thoiGianDinhDanh' => $this->when(
                $this->relationLoaded('dinhDanh'),
                fn () => $this->dinhDanh?->thoi_gian_dinh_danh?->toIso8601String(),
            ),
            'dienThoai' => $this->dien_thoai,
            'nguoiDaiDienTen' => $this->nguoi_dai_dien_ten,
            'ngaySinhNguoiDaiDien' => $this->ngay_sinh_nguoi_dai_dien,
            'chuSoHuuTen' => $this->chu_so_huu_ten,
            'nguoiDaiDien' => $this->whenLoaded('nguoiDaiDien', fn () => new MemberResource($this->nguoiDaiDien)),
            'chuSoHuu' => $this->whenLoaded('chuSoHuu', fn () => new MemberResource($this->chuSoHuu)),
            'nganhNgheKDChinh' => $this->nganh_nghe_kd_chinh,
            'nganhNgheKDChinhTen' => $this->nganh_nghe_kd_chinh
                ? ($nganhNgheCatalog->get($this->nganh_nghe_kd_chinh)?->ten
                    ?? ($this->relationLoaded('nganhNgheKdChinh') ? $this->nganhNgheKdChinh?->ten : null))
                : null,
            'nganhNgheKDChinhInfo' => $this->when(
                $this->nganh_nghe_kd_chinh && (
                    ($this->relationLoaded('nganhNgheKdChinh') && $this->nganhNgheKdChinh)
                    || $nganhNgheCatalog->has($this->nganh_nghe_kd_chinh)
                ),
                function () use ($nganhNgheCatalog) {
                    $row = $this->relationLoaded('nganhNgheKdChinh')
                        ? $this->nganhNgheKdChinh
                        : $nganhNgheCatalog->get($this->nganh_nghe_kd_chinh);

                    return $row ? new DanhMucNganhNgheResource($row) : null;
                }
            ),
            'nganhNgheKD' => $nganhNgheKdCodes,
            'nganhNgheKDList' => collect($nganhNgheKdCodes)
                ->map(fn (string $code) => [
                    'ma' => $code,
                    'ten' => $nganhNgheCatalog->get($code)?->ten,
                ])
                ->values()
                ->all(),
            'nganhNgheKDTen' => collect($nganhNgheKdCodes)
                ->map(function (string $code) use ($nganhNgheCatalog) {
                    $ten = $nganhNgheCatalog->get($code)?->ten;

                    return $ten ? "{$code} - {$ten}" : $code;
                })
                ->implode('; '),
            'ngayCap' => $this->ngay_cap,
            'ngayDangKyThayDoi' => $this->ngay_dang_ky_thay_doi,
            'loaiHinhDN' => $this->loai_hinh_dn,
            'dnLoaiHinhId' => $this->dn_loai_hinh_id,
            'dnLoaiHinh' => $this->whenLoaded('dnLoaiHinh', fn () => new DnLoaiHinhResource($this->dnLoaiHinh)),
            'soLuongLaoDong' => $this->so_luong_lao_dong,
            'loaiDN' => $this->loai_dn,
            'donViId' => $this->don_vi_id,
            'donViTen' => $this->when(
                $this->relationLoaded('donVi'),
                fn () => $this->donVi?->ten
            ),
            'donVi' => $this->whenLoaded('donVi', fn () => new DonViResource($this->donVi)),
            'createdByUserId' => $this->created_by_user_id,
            'createdByUser' => $this->whenLoaded('createdByUser', fn () => new UserResource($this->createdByUser)),
            'dsCoDong' => $this->ds_co_dong,
            'dsThanhVienGopVon' => $this->whenLoaded('memberCompanies', fn () => $this->memberCompanies->map(fn ($mc) => [
                'id' => $mc->id,
                'memberId' => $mc->member_id,
                'fullName' => $mc->member?->full_name ?? '',
                'dateJoin' => $mc->date_join,
                'position' => $mc->position,
                'investmentAmount' => $mc->investment_amount,
            ])
            ),
            'danhSachThanhVienGopVon' => $this->whenLoaded('memberCompanies', fn () => $this->memberCompanies->map(fn ($mc) => [
                'id' => $mc->id,
                'memberId' => $mc->member_id,
                'fullName' => $mc->member?->full_name ?? '',
                'dateJoin' => $mc->date_join,
                'position' => $mc->position,
                'investmentAmount' => $mc->investment_amount,
            ])
            ),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function resolveNganhNgheCatalog(): Collection
    {
        if (self::$sharedNganhNgheCatalog !== null) {
            return self::$sharedNganhNgheCatalog;
        }

        $codes = array_values(array_unique(array_filter(array_merge(
            $this->nganh_nghe_kd ?? [],
            $this->nganh_nghe_kd_chinh ? [$this->nganh_nghe_kd_chinh] : []
        ))));

        if ($codes === []) {
            return collect();
        }

        return DanhMucNganhNghe::query()
            ->whereIn('ma', $codes)
            ->get(['id', 'ma', 'ten', 'cap'])
            ->keyBy('ma');
    }

    private function resolveQuanHuyenCuMoiLabel(): ?string
    {
        $cu = $this->quan_huyen_cu
            ?? ($this->relationLoaded('quanHuyenCu') ? $this->quanHuyenCu?->full_name : null);
        $moi = $this->quan_huyen_moi
            ?? ($this->relationLoaded('tinhThanh') ? $this->tinhThanh?->full_name : null);

        if ($cu && $moi) {
            return "{$cu} / {$moi}";
        }

        if ($cu) {
            return $moi === null ? $cu : "{$cu} / {$moi}";
        }

        return $moi;
    }

    /**
     * @param  mixed  $resource
     * @return iterable<int, mixed>
     */
    private static function unwrapResourceItems($resource): iterable
    {
        if ($resource instanceof AbstractPaginator) {
            return $resource->getCollection();
        }

        if ($resource instanceof Enumerable) {
            return $resource;
        }

        if (is_array($resource)) {
            return $resource;
        }

        return [];
    }
}
