<?php

namespace App\Http\Controllers\Api;

use App\Imports\HanhChinhLegacyColumnImport;
use App\Http\Resources\QuanHuyenCuResource;
use App\Http\Resources\TinhThanhCuResource;
use App\Http\Resources\XaPhuongCuResource;
use App\Models\QuanHuyenCu;
use App\Models\TinhThanhCu;
use App\Models\XaPhuongCu;
use App\Support\HanhChinhExcelColumns;
use App\Support\HanhChinhImportColumnMap;
use App\Support\HanhChinhSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

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

    public function indexDistricts(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $query = QuanHuyenCu::query()->orderBy('full_name');

        if ($search !== '') {
            $query->where('full_name', 'like', "%{$search}%");
        }

        return $this->success(QuanHuyenCuResource::collection($query->get()));
    }

    public function indexLegacyUnits(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $query = XaPhuongCu::query()
            ->with('quanHuyen')
            ->orderBy('quan_huyen_cu_code')
            ->orderBy('full_name');

        if ($request->boolean('unmappedOnly')) {
            $query->whereDoesntHave('mappings');
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('full_name', 'like', "%{$search}%")
                    ->orWhere('unit_type', 'like', "%{$search}%")
                    ->orWhereHas('quanHuyen', fn ($q) => $q->where('full_name', 'like', "%{$search}%"));
            });
        }

        $perPage = min(max((int) $request->query('perPage', $request->query('per_page', 50)), 1), 200);

        return $this->paginated(
            XaPhuongCuResource::collection($query->paginate($perPage)),
            'Lấy danh sách đơn vị hành chính cũ thành công',
        );
    }

    /** @deprecated Giữ tương thích API cũ — không còn dùng cấp tỉnh. */
    public function indexDistrictsByProvince(string $provinceCode, Request $request): JsonResponse
    {
        return $this->indexDistricts($request);
    }

    public function indexWards(string $districtCode, Request $request): JsonResponse
    {
        $district = QuanHuyenCu::query()->find($districtCode);
        if (!$district) {
            return $this->error('Quận/huyện (cũ) không tồn tại.', 404);
        }

        $search = trim((string) $request->query('search', ''));

        $query = $district->xaPhuong()->with('mappings.xaPhuongMoi')->orderBy('full_name');

        if ($search !== '') {
            $query->where('full_name', 'like', "%{$search}%");
        }

        return $this->success(XaPhuongCuResource::collection($query->get()));
    }

    public function bulkImport(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'items' => ['required', 'array', 'min:1'],
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

    public function importExcel(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
            'startRow' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'columnMap' => ['nullable'],
        ]);

        $startRow = $request->has('startRow')
            ? (int) $request->input('startRow')
            : HanhChinhImportColumnMap::DEFAULT_START_ROW;
        $columnMap = $this->parseImportColumnMap($request->input('columnMap'));

        $import = new HanhChinhLegacyColumnImport($startRow, $columnMap);
        Excel::import($import, $request->file('file'));

        $rows = $import->rows();
        if ($rows === []) {
            return $this->error('Không đọc được dữ liệu từ file Excel hoặc file rỗng.', 422);
        }

        $counts = $this->syncService->importLegacyWithMappings($rows);

        return $this->success([
            ...$counts,
            'rows' => count($rows),
        ], 'Import Excel dữ liệu hành chính cũ và mapping thành công');
    }

    public function importColumnMap(): JsonResponse
    {
        $defaults = HanhChinhImportColumnMap::defaultExampleFormat();
        $legacyOnly = HanhChinhImportColumnMap::legacyOnlyExampleFormat();

        return $this->success([
            'startRow' => $defaults['start_row'],
            'columnMap' => HanhChinhImportColumnMap::DEFAULT_COLUMN_MAP,
            'columnLabels' => HanhChinhExcelColumns::columnLabels(),
            'legacyOnlyColumnMap' => HanhChinhImportColumnMap::LEGACY_ONLY_COLUMN_MAP,
            'legacyOnlyColumnLabels' => HanhChinhExcelColumns::legacyOnlyColumnLabels(),
            'legacyOnlyStartRow' => $legacyOnly['start_row'],
            'valueExtensions' => [],
            'defaultConfigCode' => HanhChinhImportColumnMap::EXAMPLE_CONFIG_CODE,
            'legacyOnlyConfigCode' => HanhChinhImportColumnMap::LEGACY_ONLY_CONFIG_CODE,
        ]);
    }

    /**
     * @return array<string, list<string>>|null
     */
    private function parseImportColumnMap(mixed $input): ?array
    {
        if ($input === null || $input === '') {
            return null;
        }

        if (is_string($input)) {
            $decoded = json_decode($input, true);

            return is_array($decoded) ? $decoded : null;
        }

        return is_array($input) ? $input : null;
    }
}
