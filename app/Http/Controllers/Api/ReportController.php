<?php

namespace App\Http\Controllers\Api;

use App\Exports\BaoCaoTienDoDinhDanhExport;
use App\Exports\BaoCaoTongHopExport;
use App\Support\BaoCaoTienDoDinhDanhService;
use App\Support\BaoCaoTongHopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function tienDoDinhDanh(Request $request): JsonResponse
    {
        $options = $this->progressReportOptions($request);

        return $this->success($this->tienDoService->build($options));
    }

    public function exportTienDoDinhDanh(Request $request): BinaryFileResponse
    {
        $options = $this->progressReportOptions($request);
        $filename = 'bao-cao-tien-do-dinh-danh_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new BaoCaoTienDoDinhDanhExport($this->tienDoService, $options),
            $filename
        );
    }

    /**
     * @return array{reportDate?: string, range1To?: string, range2From?: string, range2To?: string}
     */
    private function progressReportOptions(Request $request): array
    {
        $validated = $request->validate([
            'reportDate' => ['nullable', 'date'],
            'range1To' => ['nullable', 'date'],
            'range2From' => ['nullable', 'date'],
            'range2To' => ['nullable', 'date'],
        ]);

        return array_filter($validated, fn ($value) => $value !== null);
    }
}
