<?php

use App\Support\ImportUploadLogger;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

if (! function_exists('isImportUploadRequest')) {
    function isImportUploadRequest(Request $request): bool
    {
        return str_contains($request->path(), 'import');
    }
}

if (! function_exists('importContextFromRequest')) {
    function importContextFromRequest(Request $request): string
    {
        $path = $request->path();

        if (str_contains($path, 'hop-tac-xa/import')) {
            return 'hop_tac_xa_import';
        }

        if (str_contains($path, 'doanh-nghiep/import-dinh-danh')) {
            return 'doanh_nghiep_import_dinh_danh';
        }

        if (str_contains($path, 'doanh-nghiep/import')) {
            return 'doanh_nghiep_import';
        }

        if (str_contains($path, 'danh-muc-nganh-import')) {
            return 'danh_muc_nganh_import';
        }

        return 'api_import';
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (PostTooLargeException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = 'File vượt quá giới hạn upload của server (nginx client_max_body_size hoặc PHP post_max_size).';

            ImportUploadLogger::uploadRejected(
                'api_upload',
                $request,
                'post_too_large',
                $message,
            );

            return response()->json([
                'success' => false,
                'message' => $message,
                'reason' => 'post_too_large',
            ], 413);
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = collect($exception->validator->errors()->all())->first()
                ?? 'Dữ liệu không hợp lệ.';

            if (isImportUploadRequest($request)) {
                ImportUploadLogger::validationFailed(
                    importContextFromRequest($request),
                    $request,
                    $exception->validator->errors(),
                );
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'reason' => 'validation_failed',
                'errors' => $exception->validator->errors(),
            ], 422);
        });
    })->create();
