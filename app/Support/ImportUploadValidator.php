<?php

namespace App\Support;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImportUploadValidator
{
    /**
     * Validate Excel upload, log failures, return Vietnamese error response on 422.
     *
     * @param  array<string, mixed>  $extraRules
     */
    public static function validate(Request $request, string $context, array $extraRules = []): UploadedFile
    {
        if ($uploadError = self::detectUploadTransportFailure($request)) {
            ImportUploadLogger::uploadRejected(
                $context,
                $request,
                $uploadError['reason'],
                $uploadError['message'],
            );

            self::throwError($uploadError['message'], $uploadError['reason'], [
                'file' => [$uploadError['message']],
            ]);
        }

        $maxMb = 520;

        $validator = Validator::make(
            $request->all(),
            array_merge(['file' => ImportFileRules::excel()], $extraRules),
            self::messages($maxMb),
            ['file' => 'file Excel', 'startRow' => 'dòng bắt đầu'],
        );

        if ($validator->fails()) {
            $message = self::firstErrorMessage($validator->errors());
            ImportUploadLogger::validationFailed(
                $context,
                $request,
                $validator->errors(),
                'validation_failed',
            );

            self::throwError($message, 'validation_failed', $validator->errors()->toArray());
        }

        /** @var UploadedFile $file */
        $file = $request->file('file');

        return $file;
    }

    /**
     * @return array{reason: string, message: string}|null
     */
    private static function detectUploadTransportFailure(Request $request): ?array
    {
        $file = $request->file('file');

        if ($file !== null) {
            $error = $file->getError();
            if ($error === UPLOAD_ERR_OK) {
                return null;
            }

            return match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => [
                    'reason' => 'php_upload_limit',
                    'message' => sprintf(
                        'File vượt quá giới hạn upload của PHP (upload_max_filesize=%s, post_max_size=%s).',
                        ini_get('upload_max_filesize'),
                        ini_get('post_max_size'),
                    ),
                ],
                UPLOAD_ERR_PARTIAL => [
                    'reason' => 'partial_upload',
                    'message' => 'File chỉ upload được một phần. Vui lòng thử lại.',
                ],
                UPLOAD_ERR_NO_FILE => [
                    'reason' => 'no_file',
                    'message' => 'Không có file được chọn.',
                ],
                default => [
                    'reason' => 'upload_error',
                    'message' => 'Lỗi hệ thống khi nhận file upload (mã lỗi PHP: ' . $error . ').',
                ],
            };
        }

        $contentLength = (int) $request->header('Content-Length', 0);
        if ($contentLength > 0) {
            return [
                'reason' => 'file_not_received',
                'message' => 'Server không nhận được file. Có thể vượt giới hạn nginx (client_max_body_size) hoặc PHP (post_max_size / upload_max_filesize).',
            ];
        }

        return [
            'reason' => 'missing_file',
            'message' => 'Thiếu file upload. Gửi field "file" dạng multipart/form-data.',
        ];
    }

    /**
     * @param  array<string, array<int, string>>|null  $errors
     */
    public static function throwError(string $message, string $reason, ?array $errors = null): never
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'reason' => $reason,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        throw new HttpResponseException(response()->json($payload, 422));
    }

    /**
     * @return array<string, string>
     */
    private static function messages(int $maxMb): array
    {
        return [
            'file.required' => 'Vui lòng chọn file Excel để import.',
            'file.file' => 'Dữ liệu upload không hợp lệ.',
            'file.mimes' => 'Chỉ chấp nhận file Excel (.xlsx, .xls) hoặc CSV.',
            'file.extensions' => 'Chỉ chấp nhận file Excel (.xlsx, .xls) hoặc CSV.',
            'file.mimetypes' => 'Chỉ chấp nhận file Excel (.xlsx, .xls) hoặc CSV.',
            'file.max' => "File vượt quá giới hạn {$maxMb} MB.",
            'startRow.integer' => 'Dòng bắt đầu phải là số nguyên.',
            'startRow.min' => 'Dòng bắt đầu tối thiểu là 1.',
            'startRow.max' => 'Dòng bắt đầu tối đa là 1000.',
        ];
    }

    private static function firstErrorMessage(\Illuminate\Support\MessageBag $errors): string
    {
        /** @var string|null $first */
        $first = collect($errors->all())->first();

        return $first ?? 'Dữ liệu upload không hợp lệ.';
    }

    public static function fromValidationException(ValidationException $exception, Request $request, string $context): never
    {
        $message = self::firstErrorMessage($exception->validator->errors());
        ImportUploadLogger::validationFailed($context, $request, $exception->validator->errors());

        self::throwError($message, 'validation_failed', $exception->validator->errors()->toArray());
    }
}
