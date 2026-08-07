<?php

namespace App\Http\Controllers\Api;

use App\Models\PhongBan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhongBanController extends ApiController
{
    public function options(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $limit = min(max((int) $request->query('limit', 200), 1), 500);

        $query = PhongBan::query()
            ->select(['id', 'ma', 'ten'])
            ->where('is_active', true)
            ->orderBy('thu_tu')
            ->orderBy('ten');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('ma', 'like', "%{$search}%")
                    ->orWhere('ten', 'like', "%{$search}%");
            });
        }

        $items = $query->limit($limit)->get()->map(fn (PhongBan $item) => [
            'id' => $item->id,
            'ma' => $item->ma,
            'ten' => $item->ten,
        ])->values();

        return $this->success($items, 'Lấy danh sách phòng ban (options) thành công');
    }
}
