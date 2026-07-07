<?php

namespace App\Jobs;

use App\Imports\DoanhNghiepDinhDanhImport;
use App\Models\DoanhNghiepImportJob;
use App\Models\User;
use App\Support\DinhDanhHistoryContext;
use App\Support\DoanhNghiepDinhDanhImportColumnMap;
use App\Support\ImportSocketNotifier;
use App\Support\ImportSocketTopics;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ProcessDoanhNghiepIdentityImportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public int $tries = 1;

    public function __construct(
        public readonly int $importJobId,
        public readonly bool $daCapNhatDinhDanh,
    ) {}

    public function uniqueId(): string
    {
        return 'doanh-nghiep-identity-import-' . $this->importJobId;
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
            $startRow = $importJob->start_row ?? DoanhNghiepDinhDanhImportColumnMap::DEFAULT_START_ROW;
            $columnMap = $importJob->column_map;

            $import = new DoanhNghiepDinhDanhImport(
                $user,
                $startRow,
                $columnMap,
                $this->daCapNhatDinhDanh,
                $importJob->id,
            );

            $result = DinhDanhHistoryContext::run(['nguon' => 'import'], function () use ($import, $absolutePath) {
                Excel::import($import, $absolutePath);

                return $import->getResult();
            });

            $importJob->markCompleted($result);
            ImportSocketNotifier::notify(
                $user->id,
                ImportSocketTopics::EXCEL_COMPLETED,
                $importJob->id,
                [
                    'status' => DoanhNghiepImportJob::STATUS_COMPLETED,
                    'result' => $result,
                    'message' => "Import định danh hoàn tất: {$result['updated']} cập nhật, {$result['failed']} lỗi.",
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
}
