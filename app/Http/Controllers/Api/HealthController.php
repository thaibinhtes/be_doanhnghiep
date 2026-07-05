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
                'sapi' => PHP_SAPI,
                'uploadMaxFilesize' => ini_get('upload_max_filesize'),
                'postMaxSize' => ini_get('post_max_size'),
                'memoryLimit' => ini_get('memory_limit'),
                'uploadOk' => self::parseIniSize(ini_get('upload_max_filesize')) >= 520 * 1024 * 1024,
            ],
            'timestamp' => now()->toIso8601String(),
        ], 'API is running');
    }

    private static function parseIniSize(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        return match ($unit) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    }
}
