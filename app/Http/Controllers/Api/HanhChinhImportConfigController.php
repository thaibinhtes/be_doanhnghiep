<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\HanhChinhImportConfigResource;
use App\Models\HanhChinhImportConfig;
use Illuminate\Http\JsonResponse;

class HanhChinhImportConfigController extends ApiController
{
    public function index(): JsonResponse
    {
        $configs = HanhChinhImportConfig::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->success(
            HanhChinhImportConfigResource::collection($configs)->resolve(),
            'Lấy danh sách config ánh xạ import hành chính thành công',
        );
    }
}
