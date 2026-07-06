<?php

namespace App\Http\Controllers\Api;

use App\Support\DashboardDinhDanhThongKeService;
use App\Support\DashboardService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class DashboardController extends ApiController
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly DashboardDinhDanhThongKeService $dinhDanhThongKeService,
    ) {}

    public function index(): JsonResponse
    {
        return $this->success($this->dashboardService->build(request()->user()));
    }

    public function dinhDanhTheoNgay(): JsonResponse
    {
        try {
            $data = $this->dinhDanhThongKeService->buildMonthlyByDay(
                request()->user(),
                request()->query('month'),
            );
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success($data, 'Lấy thống kê định danh theo ngày thành công');
    }
}
