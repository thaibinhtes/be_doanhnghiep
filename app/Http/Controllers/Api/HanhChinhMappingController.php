<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\HanhChinhMappingResource;
use App\Models\HanhChinhMapping;
use App\Support\HanhChinhSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'groupNo' => ['nullable', 'integer'],
            'xaPhuongCuCode' => ['required', 'string', 'exists:xa_phuong_cu,code'],
            'xaPhuongMoiCode' => ['required', 'string', 'exists:xa_phuong,code'],
            'newUnitType' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
        ]);

        if (HanhChinhMapping::query()->where('xa_phuong_cu_code', $payload['xaPhuongCuCode'])->exists()) {
            return $this->error('Đơn vị hành chính cũ đã có mapping.', 422);
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
                ->first();

            HanhChinhMapping::query()->updateOrCreate(
                ['xa_phuong_cu_code' => $item['xaPhuongCuCode']],
                [
                    'group_no' => $item['groupNo'] ?? null,
                    'xa_phuong_moi_code' => $item['xaPhuongMoiCode'],
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

    public function unmappedCompanies(): JsonResponse
    {
        $result = $this->syncService->syncCompanies(true, request()->user());

        return $this->success([
            'count' => count($result['unmapped']),
            'items' => $result['unmapped'],
        ], 'Lấy danh sách doanh nghiệp chưa map được');
    }
}
