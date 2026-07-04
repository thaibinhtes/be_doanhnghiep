<?php

namespace App\Http\Controllers\Api;

use App\Support\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends ApiController
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    public function index(): JsonResponse
    {
        return $this->success($this->dashboardService->build(request()->user()));
    }
}
