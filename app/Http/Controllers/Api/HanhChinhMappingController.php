<?php

namespace App\Http\Controllers\Api;

use App\Imports\HanhChinhLegacyColumnImport;
use App\Http\Resources\HanhChinhMappingResource;
use App\Models\HanhChinhMapping;
use App\Support\HanhChinhImportColumnMap;
use App\Support\HanhChinhSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class HanhChinhMappingController extends ApiController
{
    public function __construct(private readonly HanhChinhSyncService $syncService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = HanhChinhMapping::query()
            ->with(['xaPhuongCu.quanHuyen.tinhThanh', 'xaPhuongMoi.tinhThanh'])
            ->orderBy('group_no')
            ->orderBy('id');

        if ($request->filled('xaPhuongCuCode')) {
            $query->where('xa_phuong_cu_code', $request->string('xaPhuongCuCode'));
        }

        if ($request->filled('xaPhuongMoiCode')) {
            $query->where('xa_phuong_moi_code', $request->string('xaPhuongMoiCode'));
        }

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->query('search')) . '%';
            $query->where(function ($builder) use ($search) {
                $builder
                    ->whereHas('xaPhuongCu', fn ($q) => $q->where('full_name', 'like', $search))
                    ->orWhereHas('xaPhuongMoi', fn ($q) => $q->where('full_name', 'like', $search));
            });
        }

        $perPage = min(max((int) $request->query('perPage', 50), 1), 200);

        return $this->paginated(
            HanhChinhMappingResource::collection($query->paginate($perPage)),
            'Lấy danh sách mapping thành công',
        );
    }

    public function indexGroups(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $query = HanhChinhMapping::query()
            ->with(['xaPhuongCu.quanHuyen', 'xaPhuongMoi.tinhThanh'])
            ->orderBy('group_no')
            ->orderBy('xa_phuong_moi_code')
            ->orderBy('id');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->whereHas('xaPhuongCu', fn ($q) => $q->where('full_name', 'like', "%{$search}%"))
                    ->orWhereHas('xaPhuongMoi', fn ($q) => $q->where('full_name', 'like', "%{$search}%"));
            });
        }

        $mappings = $query->get();
        $groups = [];

        foreach ($mappings as $mapping) {
            $groupKey = ($mapping->group_no ?? 'none') . ':' . $mapping->xa_phuong_moi_code;

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'groupNo' => $mapping->group_no,
                    'xaPhuongMoiCode' => $mapping->xa_phuong_moi_code,
                    'newUnitType' => $mapping->new_unit_type,
                    'xaPhuongMoi' => $mapping->xaPhuongMoi ? [
                        'code' => $mapping->xaPhuongMoi->code,
                        'fullName' => $mapping->xaPhuongMoi->full_name,
                        'unitType' => $mapping->xaPhuongMoi->unit_type,
                        'tinhThanhCode' => $mapping->xaPhuongMoi->tinh_thanh_code,
                        'tinhThanh' => $mapping->xaPhuongMoi->tinhThanh ? [
                            'code' => $mapping->xaPhuongMoi->tinhThanh->code,
                            'fullName' => $mapping->xaPhuongMoi->tinhThanh->full_name,
                        ] : null,
                    ] : null,
                    'legacyUnits' => [],
                ];
            }

            $groups[$groupKey]['legacyUnits'][] = [
                'mappingId' => $mapping->id,
                'code' => $mapping->xa_phuong_cu_code,
                'fullName' => $mapping->xaPhuongCu?->full_name,
                'unitType' => $mapping->xaPhuongCu?->unit_type,
                'quanHuyen' => $mapping->xaPhuongCu?->quanHuyen ? [
                    'code' => $mapping->xaPhuongCu->quanHuyen->code,
                    'fullName' => $mapping->xaPhuongCu->quanHuyen->full_name,
                ] : null,
            ];
        }

        return $this->success(array_values($groups), 'Lấy nhóm liên kết thành công');
    }

    public function link(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'groupNo' => ['nullable', 'integer'],
            'xaPhuongMoiCode' => ['required', 'string', 'exists:xa_phuong,code'],
            'newUnitType' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
            'xaPhuongCuCodes' => ['present', 'array'],
            'xaPhuongCuCodes.*' => ['string', 'exists:xa_phuong_cu,code'],
            'syncScopeCuCodes' => ['nullable', 'array'],
            'syncScopeCuCodes.*' => ['string', 'exists:xa_phuong_cu,code'],
        ]);

        $result = $this->syncService->linkLegacyToNew(
            $payload['xaPhuongCuCodes'],
            $payload['xaPhuongMoiCode'],
            $payload['groupNo'] ?? null,
            $payload['newUnitType'] ?? null,
            $payload['notes'] ?? null,
            $payload['syncScopeCuCodes'] ?? null,
        );

        return $this->success($result, 'Liên kết đơn vị hành chính thành công', 201);
    }

    public function importExcel(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
            'startRow' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'columnMap' => ['nullable'],
            'mode' => ['nullable', 'string', 'in:full,mapping-only'],
        ]);

        $startRow = $request->has('startRow')
            ? (int) $request->input('startRow')
            : HanhChinhImportColumnMap::DEFAULT_START_ROW;
        $columnMap = $this->parseImportColumnMap($request->input('columnMap'));
        $mode = (string) ($request->input('mode') ?? 'full');

        $import = new HanhChinhLegacyColumnImport($startRow, $columnMap);
        Excel::import($import, $request->file('file'));

        $rows = $import->rows();
        if ($rows === []) {
            return $this->error(
                'Không đọc được dữ liệu từ file Excel hoặc file rỗng. Kiểm tra dòng bắt đầu và ánh xạ cột.',
                422,
            );
        }

        $counts = $mode === 'mapping-only'
            ? $this->syncService->importMappingsOnly($rows)
            : $this->syncService->importLegacyWithMappings($rows);

        return $this->success([
            ...$counts,
            'rows' => count($rows),
        ], 'Import liên kết từ Excel thành công');
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

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'groupNo' => ['nullable', 'integer'],
            'xaPhuongCuCode' => ['required', 'string', 'exists:xa_phuong_cu,code'],
            'xaPhuongMoiCode' => ['required', 'string', 'exists:xa_phuong,code'],
            'newUnitType' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
        ]);

        if (HanhChinhMapping::query()
            ->where('xa_phuong_cu_code', $payload['xaPhuongCuCode'])
            ->where('xa_phuong_moi_code', $payload['xaPhuongMoiCode'])
            ->exists()) {
            return $this->error('Liên kết này đã tồn tại.', 422);
        }

        $mapping = HanhChinhMapping::query()->create([
            'group_no' => $payload['groupNo'] ?? null,
            'xa_phuong_cu_code' => $payload['xaPhuongCuCode'],
            'xa_phuong_moi_code' => $payload['xaPhuongMoiCode'],
            'new_unit_type' => $payload['newUnitType'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ]);

        $mapping->load(['xaPhuongCu.quanHuyen.tinhThanh', 'xaPhuongMoi.tinhThanh']);

        return $this->success(new HanhChinhMappingResource($mapping), 'Tạo mapping thành công', 201);
    }

    public function update(HanhChinhMapping $hanhChinhMapping, Request $request): JsonResponse
    {
        $payload = $request->validate([
            'groupNo' => ['nullable', 'integer'],
            'xaPhuongMoiCode' => ['sometimes', 'string', 'exists:xa_phuong,code'],
            'newUnitType' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
        ]);

        $hanhChinhMapping->update([
            'group_no' => array_key_exists('groupNo', $payload) ? $payload['groupNo'] : $hanhChinhMapping->group_no,
            'xa_phuong_moi_code' => $payload['xaPhuongMoiCode'] ?? $hanhChinhMapping->xa_phuong_moi_code,
            'new_unit_type' => array_key_exists('newUnitType', $payload) ? $payload['newUnitType'] : $hanhChinhMapping->new_unit_type,
            'notes' => array_key_exists('notes', $payload) ? $payload['notes'] : $hanhChinhMapping->notes,
        ]);

        $hanhChinhMapping->load(['xaPhuongCu.quanHuyen.tinhThanh', 'xaPhuongMoi.tinhThanh']);

        return $this->success(new HanhChinhMappingResource($hanhChinhMapping), 'Cập nhật mapping thành công');
    }

    public function destroy(HanhChinhMapping $hanhChinhMapping): JsonResponse
    {
        $hanhChinhMapping->delete();

        return $this->success(null, 'Xóa mapping thành công');
    }

    public function bulkImport(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.xaPhuongCuCode' => ['required', 'string', 'exists:xa_phuong_cu,code'],
            'items.*.xaPhuongMoiCode' => ['required', 'string', 'exists:xa_phuong,code'],
            'items.*.groupNo' => ['nullable', 'integer'],
            'items.*.newUnitType' => ['nullable', 'string', 'max:32'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $created = 0;
        $updated = 0;

        foreach ($payload['items'] as $item) {
            $existing = HanhChinhMapping::query()
                ->where('xa_phuong_cu_code', $item['xaPhuongCuCode'])
                ->where('xa_phuong_moi_code', $item['xaPhuongMoiCode'])
                ->first();

            HanhChinhMapping::query()->updateOrCreate(
                [
                    'xa_phuong_cu_code' => $item['xaPhuongCuCode'],
                    'xa_phuong_moi_code' => $item['xaPhuongMoiCode'],
                ],
                [
                    'group_no' => $item['groupNo'] ?? null,
                    'new_unit_type' => $item['newUnitType'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ],
            );

            if ($existing) {
                $updated++;
            } else {
                $created++;
            }
        }

        return $this->success(compact('created', 'updated'), 'Import mapping thành công');
    }

    public function syncCompanies(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'dryRun' => ['nullable', 'boolean'],
        ]);

        $result = $this->syncService->syncCompanies((bool) ($payload['dryRun'] ?? false), $request->user());

        return $this->success($result, ($payload['dryRun'] ?? false) ? 'Dry-run đồng bộ hoàn tất' : 'Đồng bộ doanh nghiệp thành công');
    }

    public function companyFieldSyncOptions(): JsonResponse
    {
        return $this->success($this->syncService->companyFieldSyncOptions(), 'Lấy danh sách field đồng bộ thành công');
    }

    public function syncCompanyField(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'field' => ['required', 'string', 'in:quanHuyen'],
            'sourceTable' => ['required', 'string', 'in:hanh_chinh_cu,hanh_chinh_moi'],
            'dryRun' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $this->syncService->syncCompanyField(
                $payload['field'],
                $payload['sourceTable'],
                (bool) ($payload['dryRun'] ?? false),
                $request->user(),
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(
            $result,
            ($payload['dryRun'] ?? false) ? 'Dry-run đồng bộ field hoàn tất' : 'Đồng bộ field doanh nghiệp thành công',
        );
    }

    public function unmappedCompanies(): JsonResponse
    {
        $result = $this->syncService->syncCompanies(true, request()->user());

        return $this->success([
            'count' => count($result['unmapped']),
            'items' => $result['unmapped'],
        ], 'Lấy danh sách doanh nghiệp chưa map được');
    }
}
