<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ImportSocketNotifier
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function notify(int $userId, string $topic, int $importJobId, array $payload = []): void
    {
        $message = [
            'topic' => $topic,
            'userId' => $userId,
            'importJobId' => $importJobId,
            'payload' => $payload,
            'timestamp' => now()->toIso8601String(),
        ];

        if (self::publishViaRedis($message)) {
            return;
        }

        self::publishViaHttp($message);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private static function publishViaRedis(array $message): bool
    {
        try {
            $channel = config('socket.redis_channel', 'mobi:import-events');
            Redis::connection('default')->publish($channel, json_encode($message, JSON_THROW_ON_ERROR));

            return true;
        } catch (\Throwable $exception) {
            Log::debug('Import socket redis publish failed', [
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private static function publishViaHttp(array $message): void
    {
        $url = config('socket.internal_url');
        $secret = config('socket.internal_secret');

        if (!$url || !$secret) {
            Log::debug('Import socket HTTP fallback skipped — socket.internal_url not configured');

            return;
        }

        try {
            Http::timeout(5)
                ->withHeaders(['X-Internal-Secret' => $secret])
                ->post(rtrim($url, '/') . '/internal/notify', $message)
                ->throw();
        } catch (\Throwable $exception) {
            Log::warning('Import socket HTTP notify failed', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
