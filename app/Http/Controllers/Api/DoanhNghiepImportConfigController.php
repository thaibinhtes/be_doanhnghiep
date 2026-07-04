<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\DoanhNghiepImportConfigResource;
use App\Models\DoanhNghiepImportConfig;
use Illuminate\Http\JsonResponse;

class DoanhNghiepImportConfigController extends ApiController
{
    public function index(): JsonResponse
    {
        $configs = DoanhNghiepImportConfig::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->success(
            DoanhNghiepImportConfigResource::collection($configs)->resolve(),
            'Lấy danh sách config ánh xạ import thành công',
        );
    }
}
