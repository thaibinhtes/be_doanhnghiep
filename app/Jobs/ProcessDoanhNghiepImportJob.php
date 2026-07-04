<?php

namespace App\Jobs;

use App\Imports\DoanhNghiepColumnImport;
use App\Imports\DoanhNghiepImport;
use App\Models\DoanhNghiepImportJob;
use App\Models\User;
use App\Support\DinhDanhHistoryContext;
use App\Support\DoanhNghiepImportColumnMap;
use App\Support\ImportSocketNotifier;
use App\Support\ImportSocketTopics;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ProcessDoanhNghiepImportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Max seconds for large Excel imports (worker must use --timeout >= this value). */
    public int $timeout = 7200;

    public int $tries = 1;

    public function __construct(
        public readonly int $importJobId,
    ) {}

    public function uniqueId(): string
    {
        return 'doanh-nghiep-import-' . $this->importJobId;
    }

    public function handle(): void
    {
        $importJob = DoanhNghiepImportJob::query()->find($this->importJobId);

        if (!$importJob || !in_array($importJob->status, [
            DoanhNghiepImportJob::STATUS_PENDING,
            DoanhNghiepImportJob::STATUS_PROCESSING,
        ], true)) {
            return;
        }

        $user = User::query()->find($importJob->user_id);

        if (!$user) {
            $importJob->markFailed('Người dùng không tồn tại.');

            return;
        }

        $importJob->markProcessing();

        ImportSocketNotifier::notify(
            $user->id,
            ImportSocketTopics::EXCEL_STARTED,
            $importJob->id,
            [
                'status' => DoanhNghiepImportJob::STATUS_PROCESSING,
                'originalFilename' => $importJob->original_filename,
            ],
        );

        $disk = Storage::disk('local');
        $absolutePath = $disk->path($importJob->file_path);

        if (!is_file($absolutePath)) {
            $this->failJob($importJob, $user->id, 'File import không tồn tại.');

            return;
        }

        try {
            $startRow = $importJob->start_row ?? DoanhNghiepImportColumnMap::DEFAULT_START_ROW;
            $columnMap = $importJob->column_map;
            $valueExtensions = $importJob->value_extensions;

            $import = $importJob->use_column_map
                ? new DoanhNghiepColumnImport($user, $startRow, $columnMap, $valueExtensions, $importJob->id)
                : new DoanhNghiepImport($user, $valueExtensions, $importJob->id);

            $result = DinhDanhHistoryContext::run(['nguon' => 'import'], function () use ($import, $absolutePath) {
                Excel::import($import, $absolutePath);

                return $import->getResult();
            });

            $duplicates = $result['duplicates'] ?? ($result['updated'] ?? 0);
            $importJob->markCompleted($result);

            ImportSocketNotifier::notify(
                $user->id,
                ImportSocketTopics::EXCEL_COMPLETED,
                $importJob->id,
                [
                    'status' => DoanhNghiepImportJob::STATUS_COMPLETED,
                    'result' => $result,
                    'message' => "Import hoàn tất: {$result['imported']} mới, {$duplicates} trùng, {$result['failed']} lỗi.",
                ],
            );
        } catch (\Throwable $exception) {
            $this->failJob($importJob, $user->id, $exception->getMessage());
        } finally {
            $disk->delete($importJob->file_path);
        }
    }

    private function failJob(DoanhNghiepImportJob $importJob, int $userId, string $message): void
    {
        $importJob->markFailed($message);

        ImportSocketNotifier::notify(
            $userId,
            ImportSocketTopics::EXCEL_FAILED,
            $importJob->id,
            [
                'status' => DoanhNghiepImportJob::STATUS_FAILED,
                'message' => $message,
            ],
        );
    }

    public function failed(?\Throwable $exception): void
    {
        $importJob = DoanhNghiepImportJob::query()->find($this->importJobId);

        if (!$importJob || $importJob->status === DoanhNghiepImportJob::STATUS_COMPLETED) {
            return;
        }

        $message = $exception?->getMessage() ?? 'Import thất bại.';

        $this->failJob($importJob, $importJob->user_id, $message);
    }
}
