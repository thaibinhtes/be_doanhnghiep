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

    /** Overview nhanh (không gồm breakdown địa bàn). */
    public function index(): JsonResponse
    {
        return $this->success($this->dashboardService->buildOverview(request()->user()));
    }

    public function companyAreas(): JsonResponse
    {
        try {
            $data = $this->dashboardService->buildCompanyAreas(
                request()->user(),
                (string) request()->query('areaKey', 'quanHuyenMoi'),
            );
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success($data, 'Lấy thống kê địa bàn doanh nghiệp thành công');
    }

    public function cooperativeAreas(): JsonResponse
    {
        try {
            $data = $this->dashboardService->buildCooperativeAreas(
                request()->user(),
                (string) request()->query('areaKey', 'quanHuyenMoi'),
            );
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success($data, 'Lấy thống kê địa bàn hợp tác xã thành công');
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
