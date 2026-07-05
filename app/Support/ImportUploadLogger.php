<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;

class ImportUploadLogger
{
    private const CHANNEL = 'import_upload';

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function validationFailed(string $context, Request $request, MessageBag $errors, ?string $reason = null, array $extra = []): void
    {
        Log::channel(self::CHANNEL)->warning('Excel upload validation failed', self::baseContext($context, $request, [
            'reason' => $reason ?? 'validation_failed',
            'errors' => $errors->toArray(),
            ...$extra,
        ]));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function uploadRejected(string $context, Request $request, string $reason, string $message, array $extra = []): void
    {
        Log::channel(self::CHANNEL)->warning('Excel upload rejected', self::baseContext($context, $request, [
            'reason' => $reason,
            'message' => $message,
            ...$extra,
        ]));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function exception(string $context, Request $request, \Throwable $exception, ?string $step = null, array $extra = []): void
    {
        Log::channel(self::CHANNEL)->error('Excel upload exception', self::baseContext($context, $request, [
            'reason' => 'exception',
            'step' => $step,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            ...$extra,
        ]));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function succeeded(string $context, Request $request, array $extra = []): void
    {
        Log::channel(self::CHANNEL)->info('Excel upload accepted', self::baseContext($context, $request, $extra));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function baseContext(string $context, Request $request, array $extra = []): array
    {
        $file = $request->file('file');

        return [
            'context' => $context,
            'user_id' => $request->user()?->id,
            'user_email' => $request->user()?->email,
            'ip' => $request->ip(),
            'path' => $request->path(),
            'method' => $request->method(),
            'content_length' => $request->header('Content-Length'),
            'content_type' => $request->header('Content-Type'),
            'php_upload_max_filesize' => ini_get('upload_max_filesize'),
            'php_post_max_size' => ini_get('post_max_size'),
            'file_original_name' => $file?->getClientOriginalName(),
            'file_client_mime' => $file?->getClientMimeType(),
            'file_size_bytes' => $file?->getSize(),
            'file_upload_error' => $file?->getError(),
            ...$extra,
        ];
    }
}
