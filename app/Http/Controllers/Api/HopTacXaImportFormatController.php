<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\HopTacXaImportFormatResource;
use App\Models\HopTacXaImportFormat;
use App\Support\HopTacXaImportColumnMap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HopTacXaImportFormatController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $formats = HopTacXaImportFormat::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get();

        return $this->success(
            HopTacXaImportFormatResource::collection($formats)->resolve(),
            'Lấy danh sách format import HTX thành công',
        );
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'startRow' => ['required', 'integer', 'min:1', 'max:1000'],
            'columnMap' => ['required', 'array', 'min:1'],
            'valueExtensions' => ['nullable', 'array'],
        ]);

        $user = $request->user();

        if (HopTacXaImportFormat::query()->where('user_id', $user->id)->where('name', $payload['name'])->exists()) {
            return $this->error('Đã tồn tại format cùng tên.', 422);
        }

        $format = HopTacXaImportFormat::query()->create([
            'user_id' => $user->id,
            'don_vi_id' => $user->don_vi_id,
            'name' => $payload['name'],
            'start_row' => $payload['startRow'],
            'column_map' => HopTacXaImportColumnMap::normalizeStoredColumnMap($payload['columnMap']),
            'value_extensions' => $payload['valueExtensions'] ?? null,
        ]);

        return $this->success(
            new HopTacXaImportFormatResource($format),
            'Lưu format import thành công',
            201,
        );
    }

    public function update(Request $request, HopTacXaImportFormat $importFormat): JsonResponse
    {
        $this->authorizeFormat($request, $importFormat);

        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'startRow' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'columnMap' => ['sometimes', 'array', 'min:1'],
            'valueExtensions' => ['nullable', 'array'],
        ]);

        if (isset($payload['name'])) {
            $exists = HopTacXaImportFormat::query()
                ->where('user_id', $request->user()->id)
                ->where('name', $payload['name'])
                ->where('id', '!=', $importFormat->id)
                ->exists();

            if ($exists) {
                return $this->error('Đã tồn tại format cùng tên.', 422);
            }

            $importFormat->name = $payload['name'];
        }

        if (isset($payload['startRow'])) {
            $importFormat->start_row = $payload['startRow'];
        }

        if (isset($payload['columnMap'])) {
            $importFormat->column_map = HopTacXaImportColumnMap::normalizeStoredColumnMap($payload['columnMap']);
        }

        if (array_key_exists('valueExtensions', $payload)) {
            $importFormat->value_extensions = $payload['valueExtensions'];
        }

        $importFormat->save();

        return $this->success(
            new HopTacXaImportFormatResource($importFormat->fresh()),
            'Cập nhật format import thành công',
        );
    }

    public function destroy(Request $request, HopTacXaImportFormat $importFormat): JsonResponse
    {
        $this->authorizeFormat($request, $importFormat);
        $importFormat->delete();

        return $this->success(null, 'Xóa format import thành công');
    }

    private function authorizeFormat(Request $request, HopTacXaImportFormat $importFormat): void
    {
        if ((int) $importFormat->user_id !== (int) $request->user()->id) {
            abort(403, 'Không có quyền thao tác format này.');
        }
    }
}
