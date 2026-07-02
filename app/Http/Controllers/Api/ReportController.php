<?php

namespace App\Http\Controllers\Api;

use App\Exports\BaoCaoTienDoDinhDanhExport;
use App\Exports\BaoCaoTongHopExport;
use App\Support\BaoCaoTienDoDinhDanhService;
use App\Support\BaoCaoTongHopService;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends ApiController
{
    public function __construct(
        private readonly BaoCaoTongHopService $baoCaoService,
        private readonly BaoCaoTienDoDinhDanhService $tienDoService,
    ) {}

    public function tongHop(): JsonResponse
    {
        return $this->success($this->baoCaoService->build());
    }

    public function exportTongHop(): BinaryFileResponse
    {
        $filename = 'bao-cao-tong-hop_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new BaoCaoTongHopExport($this->baoCaoService),
            $filename
        );
    }

    public function tienDoDinhDanh(): JsonResponse
    {
        return $this->success($this->tienDoService->build());
    }

    public function exportTienDoDinhDanh(): BinaryFileResponse
    {
        $filename = 'bao-cao-tien-do-dinh-danh_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new BaoCaoTienDoDinhDanhExport($this->tienDoService),
            $filename
        );
    }
}
