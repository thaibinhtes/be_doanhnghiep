<?php

namespace App\Jobs;

use App\Imports\DoanhNghiepFieldUpdateImport;
use App\Models\DoanhNghiepImportJob;
use App\Models\User;
use App\Support\DoanhNghiepFieldUpdateImportColumnMap;
use App\Support\ImportSocketNotifier;
use App\Support\ImportSocketTopics;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ProcessDoanhNghiepFieldUpdateImportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public int $tries = 1;

    public int $uniqueFor = 7200;

    public function __construct(
        public readonly int $importJobId,
    ) {
        $this->onQueue('doanh-nghiep');
    }

    public function uniqueId(): string
    {
        return 'doanh-nghiep-field-update-import-' . $this->importJobId;
    }

    public function handle(): void
    {
        @ini_set('memory_limit', '512M');

        $importJob = DoanhNghiepImportJob::query()->find($this->importJobId);
        if (!$importJob || in_array($importJob->status, [
            DoanhNghiepImportJob::STATUS_COMPLETED,
            DoanhNghiepImportJob::STATUS_FAILED,
        ], true)) {
            return;
        }

        if (!$importJob->tryClaimForProcessing()) {
            return;
        }

        $user = User::query()->find($importJob->user_id);
        if (!$user) {
            $importJob->markFailed('Người dùng không tồn tại.');

            return;
        }

        ImportSocketNotifier::notify(
            $user->id,
            ImportSocketTopics::EXCEL_STARTED,
            $importJob->id,
            [
                'status' => DoanhNghiepImportJob::STATUS_PROCESSING,
                'originalFilename' => $importJob->original_filename,
                'entity' => 'doanh-nghiep',
            ],
        );

        $disk = Storage::disk('local');
        $absolutePath = $disk->path($importJob->file_path);
        if (!is_file($absolutePath)) {
            $this->failJob($importJob, $user->id, 'File import không tồn tại.');

            return;
        }

        try {
            $startRow = $importJob->start_row ?? DoanhNghiepFieldUpdateImportColumnMap::DEFAULT_START_ROW;
            $columnMap = is_array($importJob->column_map) ? $importJob->column_map : null;
            $lookupField = DoanhNghiepFieldUpdateImportColumnMap::DEFAULT_LOOKUP_FIELD;
            $extensions = is_array($importJob->value_extensions) ? $importJob->value_extensions : [];
            if (isset($extensions['lookupField']) && is_string($extensions['lookupField'])) {
                $lookupField = $extensions['lookupField'];
            }

            $import = new DoanhNghiepFieldUpdateImport(
                $user,
                $startRow,
                $columnMap,
                $lookupField,
                $importJob->id,
            );

            Excel::import($import, $absolutePath);
            $result = $import->getResult();

            $importJob->markCompleted($result);
            ImportSocketNotifier::notify(
                $user->id,
                ImportSocketTopics::EXCEL_COMPLETED,
                $importJob->id,
                [
                    'status' => DoanhNghiepImportJob::STATUS_COMPLETED,
                    'result' => $result,
                    'message' => "Cập nhật field hoàn tất: {$result['updated']} cập nhật, {$result['skipped']} bỏ qua, {$result['failed']} lỗi.",
                    'entity' => 'doanh-nghiep',
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
        $importJob->refresh();

        if ($importJob->status === DoanhNghiepImportJob::STATUS_COMPLETED) {
            return;
        }

        $importJob->markFailed($message);
        ImportSocketNotifier::notify(
            $userId,
            ImportSocketTopics::EXCEL_FAILED,
            $importJob->id,
            [
                'status' => DoanhNghiepImportJob::STATUS_FAILED,
                'message' => $message,
                'entity' => 'doanh-nghiep',
            ],
        );
    }

    public function failed(?\Throwable $exception): void
    {
        $importJob = DoanhNghiepImportJob::query()->find($this->importJobId);

        if (!$importJob) {
            return;
        }

        if ($importJob->status === DoanhNghiepImportJob::STATUS_COMPLETED || is_array($importJob->result)) {
            return;
        }

        if ($exception instanceof MaxAttemptsExceededException) {
            return;
        }

        $message = $exception?->getMessage() ?? 'Import thất bại.';

        $this->failJob($importJob, $importJob->user_id, $message);
    }
}
