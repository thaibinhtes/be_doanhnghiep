<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\TinhThanhResource;
use App\Http\Resources\XaPhuongResource;
use App\Models\TinhThanh;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TinhThanhController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $query = TinhThanh::query()->orderBy('code');

        if ($search !== '') {
            $query->where('full_name', 'like', "%{$search}%");
        }

        return $this->success(TinhThanhResource::collection($query->get()));
    }

    public function xaPhuong(string $code, Request $request): JsonResponse
    {
        $province = TinhThanh::query()->find($code);

        if (!$province) {
            return $this->error('Tỉnh/thành không tồn tại.', 404);
        }

        $search = trim((string) $request->query('search', ''));

        $query = $province->xaPhuong()->orderBy('full_name');

        if ($search !== '') {
            $query->where('full_name', 'like', "%{$search}%");
        }

        return $this->success(XaPhuongResource::collection($query->get()));
    }
}
