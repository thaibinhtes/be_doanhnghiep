<?php

namespace App\Http\Controllers\Api;

use App\Support\HanhChinhSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class HanhChinhMoiController extends ApiController
{
    public function __construct(private readonly HanhChinhSyncService $syncService)
    {
    }

    public function bulkImport(Request $request): JsonResponse
    {
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
}
