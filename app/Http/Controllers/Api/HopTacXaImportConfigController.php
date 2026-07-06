<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\HopTacXaImportConfigResource;
use App\Models\HopTacXaImportConfig;
use Illuminate\Http\JsonResponse;

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
}
