<?php

namespace App\Http\Controllers\Api;

use App\Exports\BaoCaoTienDoDinhDanhExport;
use App\Exports\BaoCaoTongHopExport;
use App\Http\Resources\DnDinhDanhLichSuResource;
use App\Support\BaoCaoTienDoDinhDanhService;
use App\Support\BaoCaoTongHopService;
use App\Support\DinhDanhLichSuReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends ApiController
{
    public function __construct(
        private readonly BaoCaoTongHopService $baoCaoService,
        private readonly BaoCaoTienDoDinhDanhService $tienDoService,
        private readonly DinhDanhLichSuReportService $dinhDanhLichSuService,
    ) {}

    public function tongHop(): JsonResponse
    {
        return $this->success($this->baoCaoService->build(request()->user()));
    }

    public function exportTongHop(): BinaryFileResponse
    {
        $filename = 'bao-cao-tong-hop_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new BaoCaoTongHopExport($this->baoCaoService, request()->user()),
            $filename
        );
    }

    public function tienDoDinhDanh(Request $request): JsonResponse
    {
        $options = $this->progressReportOptions($request);

        return $this->success($this->tienDoService->build($options, request()->user()));
    }

    public function exportTienDoDinhDanh(Request $request): BinaryFileResponse
    {
        $options = $this->progressReportOptions($request);
        $filename = 'bao-cao-tien-do-dinh-danh_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new BaoCaoTienDoDinhDanhExport($this->tienDoService, $options, request()->user()),
            $filename
        );
    }

    public function dinhDanhLichSu(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'donViId' => ['nullable', 'integer', 'exists:don_vis,id'],
            'nguon' => ['nullable', 'string', 'in:thu_cong,hang_loat,import,tao_moi,cap_nhat,he_thong'],
            'hanhDong' => ['nullable', 'string', 'in:dang_ky,huy_dang_ky'],
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = min(max((int) ($validated['perPage'] ?? $validated['per_page'] ?? 20), 1), 100);
        unset($validated['page'], $validated['perPage'], $validated['per_page']);

        $logs = $this->dinhDanhLichSuService->list(
            $request->user(),
            array_filter($validated, fn ($value) => $value !== null && $value !== ''),
            $perPage,
        );

        return $this->paginated(
            DnDinhDanhLichSuResource::collection($logs),
            'Lấy lịch sử định danh doanh nghiệp thành công',
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
