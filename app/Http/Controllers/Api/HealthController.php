<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends ApiController
{
    /**
     * Check API and database health.
     */
    public function check(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            $dbStatus = 'connected';
        } catch (\Exception $e) {
            $dbStatus = 'disconnected';
        }

        return $this->success([
            'app' => config('app.name'),
            'environment' => config('app.env'),
            'version' => config('app.version', '1.0.0'),
            'database' => $dbStatus,
            'uploadMaxMb' => 520,
            'php' => [
                'uploadMaxFilesize' => ini_get('upload_max_filesize'),
                'postMaxSize' => ini_get('post_max_size'),
                'memoryLimit' => ini_get('memory_limit'),
            ],
            'timestamp' => now()->toIso8601String(),
        ], 'API is running');
    }
}
