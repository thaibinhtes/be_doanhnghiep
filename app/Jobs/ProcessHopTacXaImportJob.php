<?php

namespace App\Jobs;

use App\Imports\HopTacXaColumnImport;
use App\Models\HopTacXaImportJob;
use App\Models\User;
use App\Support\HopTacXaImportColumnMap;
use App\Support\ImportSocketNotifier;
use App\Support\ImportSocketTopics;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ProcessHopTacXaImportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public int $tries = 1;

    public function __construct(
        public readonly int $importJobId,
    ) {}

    public function uniqueId(): string
    {
        return 'hop-tac-xa-import-' . $this->importJobId;
    }

    public function handle(): void
    {
        $importJob = HopTacXaImportJob::query()->find($this->importJobId);

        if (!$importJob || !in_array($importJob->status, [
            HopTacXaImportJob::STATUS_PENDING,
            HopTacXaImportJob::STATUS_PROCESSING,
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
                'status' => HopTacXaImportJob::STATUS_PROCESSING,
                'originalFilename' => $importJob->original_filename,
                'entity' => 'hop-tac-xa',
            ],
        );

        $disk = Storage::disk('local');
        $absolutePath = $disk->path($importJob->file_path);

        if (!is_file($absolutePath)) {
            $this->failJob($importJob, $user->id, 'File import không tồn tại.');

            return;
        }

        try {
            $startRow = $importJob->start_row ?? HopTacXaImportColumnMap::DEFAULT_START_ROW;
            $columnMap = $importJob->column_map;

            $import = new HopTacXaColumnImport($user, $startRow, $columnMap, $importJob->id);

            Excel::import($import, $absolutePath);
            $result = $import->getResult();

            $duplicates = $result['duplicates'] ?? ($result['updated'] ?? 0);
            $importJob->markCompleted($result);

            ImportSocketNotifier::notify(
                $user->id,
                ImportSocketTopics::EXCEL_COMPLETED,
                $importJob->id,
                [
                    'status' => HopTacXaImportJob::STATUS_COMPLETED,
                    'result' => $result,
                    'message' => "Import hoàn tất: {$result['imported']} mới, {$duplicates} trùng, {$result['failed']} lỗi.",
                    'entity' => 'hop-tac-xa',
                ],
            );
        } catch (\Throwable $exception) {
            $this->failJob($importJob, $user->id, $exception->getMessage());
        } finally {
            $disk->delete($importJob->file_path);
        }
    }

    private function failJob(HopTacXaImportJob $importJob, int $userId, string $message): void
    {
        $importJob->markFailed($message);

        ImportSocketNotifier::notify(
            $userId,
            ImportSocketTopics::EXCEL_FAILED,
            $importJob->id,
            [
                'status' => HopTacXaImportJob::STATUS_FAILED,
                'message' => $message,
                'entity' => 'hop-tac-xa',
            ],
        );
    }

    public function failed(?\Throwable $exception): void
    {
        $importJob = HopTacXaImportJob::query()->find($this->importJobId);

        if (!$importJob || $importJob->status === HopTacXaImportJob::STATUS_COMPLETED) {
            return;
        }

        $message = $exception?->getMessage() ?? 'Import thất bại.';

        $this->failJob($importJob, $importJob->user_id, $message);
    }
}
