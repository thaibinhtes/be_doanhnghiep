<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\HopTacXaImportConfigResource;
use App\Models\HopTacXaImportConfig;
use App\Support\HopTacXaImportColumnMap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HopTacXaImportConfigController extends ApiController
{
    public function index(): JsonResponse
    {
        $configs = HopTacXaImportConfig::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->success(
            HopTacXaImportConfigResource::collection($configs)->resolve(),
            'Lấy danh sách config ánh xạ import HTX thành công',
        );
    }

    public function indexAdmin(): JsonResponse
    {
        $configs = HopTacXaImportConfig::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->success(
            HopTacXaImportConfigResource::collection($configs)->resolve(),
            'Lấy danh sách cấu hình format ánh xạ HTX thành công',
        );
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatedPayload($request);

        $config = HopTacXaImportConfig::query()->create([
            'name' => $payload['name'],
            'code' => $payload['code'],
            'description' => $payload['description'] ?? null,
            'start_row' => $payload['startRow'],
            'column_map' => HopTacXaImportColumnMap::normalizeStoredColumnMap($payload['columnMap']),
            'value_extensions' => $payload['valueExtensions'] ?? null,
            'is_active' => $payload['isActive'] ?? true,
            'sort_order' => $payload['sortOrder'] ?? 0,
        ]);

        return $this->success(
            new HopTacXaImportConfigResource($config),
            'Tạo cấu hình format ánh xạ HTX thành công',
            201,
        );
    }

    public function update(Request $request, HopTacXaImportConfig $importConfig): JsonResponse
    {
        $payload = $this->validatedPayload($request, $importConfig->id);

        $importConfig->fill([
            'name' => $payload['name'],
            'code' => $payload['code'],
            'description' => $payload['description'] ?? null,
            'start_row' => $payload['startRow'],
            'column_map' => HopTacXaImportColumnMap::normalizeStoredColumnMap($payload['columnMap']),
            'value_extensions' => $payload['valueExtensions'] ?? null,
            'is_active' => $payload['isActive'] ?? $importConfig->is_active,
            'sort_order' => $payload['sortOrder'] ?? $importConfig->sort_order,
        ])->save();

        return $this->success(
            new HopTacXaImportConfigResource($importConfig->fresh()),
            'Cập nhật cấu hình format ánh xạ HTX thành công',
        );
    }

    public function destroy(HopTacXaImportConfig $importConfig): JsonResponse
    {
        $importConfig->delete();

        return $this->success(null, 'Xóa cấu hình format ánh xạ HTX thành công');
    }

    /** @return array<string, mixed> */
    private function validatedPayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9][a-z0-9_-]*$/',
                Rule::unique('hop_tac_xa_import_configs', 'code')->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'startRow' => ['required', 'integer', 'min:1', 'max:1000'],
            'columnMap' => ['required', 'array', 'min:1'],
            'valueExtensions' => ['nullable', 'array'],
            'isActive' => ['nullable', 'boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
    }
}
