<?php

namespace App\Http\Controllers\Api;

use App\Imports\HanhChinhNewColumnImport;
use App\Http\Resources\XaPhuongResource;
use App\Models\XaPhuong;
use App\Support\HanhChinhExcelColumns;
use App\Support\HanhChinhImportColumnMap;
use App\Support\HanhChinhNewDataClearService;
use App\Support\HanhChinhSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;

class HanhChinhMoiController extends ApiController
{
    public function __construct(private readonly HanhChinhSyncService $syncService)
    {
    }

    public function indexNewUnits(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $query = XaPhuong::query()->orderBy('full_name');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('full_name', 'like', "%{$search}%")
                    ->orWhere('unit_type', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->query('perPage', $request->query('per_page', 50)), 1), 200);

        return $this->paginated(
            XaPhuongResource::collection($query->paginate($perPage)),
            'Lấy danh sách đơn vị hành chính mới thành công',
        );
    }

    public function clearPreview(): JsonResponse
    {
        return $this->success(
            HanhChinhNewDataClearService::preview(),
            'Xem trước xóa dữ liệu hành chính mới',
        );
    }

    public function clear(): JsonResponse
    {
        $result = HanhChinhNewDataClearService::clear();

        return $this->success($result, 'Đã xóa dữ liệu hành chính mới. Có thể import lại.');
    }

    public function bulkImport(Request $request): JsonResponse
    {
        if ($request->has('items')) {
            $payload = $request->validate([
                'items' => ['required', 'array', 'min:1'],
                'items.*.xaPhuongMoi' => ['nullable', 'string'],
                'items.*.xa_phuong_moi' => ['nullable', 'string'],
                'items.*.loaiMoi' => ['nullable', 'string'],
                'items.*.loai_moi' => ['nullable', 'string'],
            ]);

            $counts = $this->syncService->importNewUnitsOnly($payload['items']);

            return $this->success($counts, 'Import đơn vị hành chính mới thành công');
        }

        $payload = $request->validate([
            'provinces' => ['required', 'array', 'min:1'],
            'provinces.*.code' => ['nullable', 'string', 'max:20'],
            'provinces.*.fullName' => ['nullable', 'string'],
            'provinces.*.full_name' => ['nullable', 'string'],
            'provinces.*.wards' => ['nullable', 'array'],
            'provinces.*.Wards' => ['nullable', 'array'],
            'provinces.*.wards.*.code' => ['nullable', 'string', 'max:20'],
            'provinces.*.wards.*.fullName' => ['nullable', 'string'],
            'provinces.*.wards.*.full_name' => ['nullable', 'string'],
        ]);

        $counts = $this->syncService->importNewAdministrativeData($payload['provinces']);

        return $this->success($counts, 'Import dữ liệu hành chính mới thành công');
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

        $import = new HanhChinhNewColumnImport($startRow, $columnMap);
        Excel::import($import, $request->file('file'));

        $rows = $import->rows();
        if ($rows === []) {
            return $this->error(
                'Không đọc được dữ liệu từ file Excel hoặc file rỗng. Kiểm tra dòng bắt đầu và ánh xạ cột (A/B cho file 2 cột, F/G cho file mapping đầy đủ).',
                422,
            );
        }

        $counts = $this->syncService->importNewUnitsOnly($rows);

        return $this->success([
            ...$counts,
            'rows' => count($rows),
        ], 'Import Excel đơn vị hành chính mới thành công');
    }

    public function importColumnMap(): JsonResponse
    {
        $newOnly = HanhChinhImportColumnMap::newOnlyExampleFormat();
        $newFromMapping = HanhChinhImportColumnMap::newFromMappingExampleFormat();

        return $this->success([
            'startRow' => $newFromMapping['start_row'],
            'columnMap' => HanhChinhImportColumnMap::NEW_FROM_MAPPING_COLUMN_MAP,
            'columnLabels' => HanhChinhExcelColumns::newOnlyColumnLabels(),
            'standaloneColumnMap' => HanhChinhImportColumnMap::NEW_ONLY_COLUMN_MAP,
            'mappingColumnMap' => HanhChinhImportColumnMap::NEW_FROM_MAPPING_COLUMN_MAP,
            'standaloneStartRow' => $newOnly['start_row'],
            'mappingStartRow' => $newFromMapping['start_row'],
            'valueExtensions' => [],
            'defaultConfigCode' => HanhChinhImportColumnMap::NEW_FROM_MAPPING_CONFIG_CODE,
            'standaloneConfigCode' => HanhChinhImportColumnMap::NEW_ONLY_CONFIG_CODE,
        ]);
    }

    public function importFromDataset(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'provinceCodes' => ['nullable', 'array'],
            'provinceCodes.*' => ['string', 'max:20'],
        ]);

        $path = database_path('data/vn_provinces.json');
        if (!File::exists($path)) {
            return $this->error('Không tìm thấy file vn_provinces.json', 404);
        }

        $raw = json_decode(File::get($path), true);
        if (!is_array($raw)) {
            return $this->error('File vn_provinces.json không hợp lệ', 422);
        }

        $allowed = collect($payload['provinceCodes'] ?? [])->filter()->values()->all();
        $provinces = [];

        foreach ($raw as $provinceRow) {
            $provinceCode = (string) ($provinceRow['Code'] ?? '');
            $provinceName = trim((string) ($provinceRow['FullName'] ?? ''));

            if ($provinceCode === '' || $provinceName === '') {
                continue;
            }

            if ($allowed !== [] && !in_array($provinceCode, $allowed, true)) {
                continue;
            }

            $wards = [];
            foreach ($provinceRow['Wards'] ?? [] as $wardRow) {
                $wardCode = (string) ($wardRow['Code'] ?? '');
                $wardName = trim((string) ($wardRow['FullName'] ?? ''));
                if ($wardCode === '' || $wardName === '') {
                    continue;
                }
                $wards[] = ['code' => $wardCode, 'fullName' => $wardName];
            }

            $provinces[] = [
                'code' => $provinceCode,
                'fullName' => $provinceName,
                'wards' => $wards,
            ];
        }

        $counts = $this->syncService->importNewAdministrativeData($provinces);

        return $this->success($counts, 'Import dữ liệu hành chính mới từ dataset thành công');
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
