<?php

namespace App\Jobs;

use App\Imports\TaxUnitColumnImport;
use App\Models\TaxImportJob;
use App\Models\TaxUnit;
use App\Models\User;
use App\Support\ImportSocketNotifier;
use App\Support\ImportSocketTopics;
use App\Support\TaxImportColumnMap;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ProcessTaxUnitImportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;
    public int $tries = 1;

    public function __construct(
        public readonly int $importJobId,
    ) {}

    public function uniqueId(): string
    {
        return 'tax-unit-import-' . $this->importJobId;
    }

    public function handle(): void
    {
        // Large XLSX files can exceed default 128MB.
        @ini_set('memory_limit', '512M');

        $importJob = TaxImportJob::query()->find($this->importJobId);
        if (!$importJob || !in_array($importJob->status, [TaxImportJob::STATUS_PENDING, TaxImportJob::STATUS_PROCESSING], true)) {
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
                'status' => TaxImportJob::STATUS_PROCESSING,
                'originalFilename' => $importJob->original_filename,
                'entity' => 'tax-unit',
            ],
        );

        $disk = Storage::disk('local');
        $absolutePath = $disk->path($importJob->file_path);
        if (!is_file($absolutePath)) {
            $this->failJob($importJob, $user->id, 'File import không tồn tại.');

            return;
        }

        try {
            $startRow = $importJob->start_row ?? TaxImportColumnMap::DEFAULT_START_ROW;
            $columnMap = $importJob->column_map;

            $import = new TaxUnitColumnImport($startRow, $columnMap);
            Excel::import($import, $absolutePath);
            $rows = $import->rows();

            $created = 0;
            $duplicates = 0;
            $skipped = 0;

            foreach ($rows as $rowIndex => $row) {
                $excelRow = $startRow + $rowIndex;
                $unitCode = trim((string) ($row['unitCode'] ?? ''));
                $unitName = trim((string) ($row['unitName'] ?? ''));

                if ($unitCode === '' || $unitName === '') {
                    $skipped++;
                    ImportSocketNotifier::notify(
                        $user->id,
                        ImportSocketTopics::EXCEL_ROW_FAILED,
                        $importJob->id,
                        [
                            'row' => $excelRow,
                            'entity' => 'tax-unit',
                            'unitCode' => $unitCode ?: null,
                            'unitName' => $unitName ?: null,
                            'message' => 'Thiếu mã hoặc tên đơn vị thuế',
                        ],
                    );
                    continue;
                }

                $exists = TaxUnit::query()->where('unit_code', $unitCode)->exists();
                if ($exists) {
                    $duplicates++;
                    ImportSocketNotifier::notify(
                        $user->id,
                        ImportSocketTopics::EXCEL_ROW_DUPLICATE,
                        $importJob->id,
                        [
                            'row' => $excelRow,
                            'entity' => 'tax-unit',
                            'unitCode' => $unitCode,
                            'unitName' => $unitName,
                            'message' => 'Mã đơn vị thuế đã tồn tại, bỏ qua.',
                        ],
                    );
                    continue;
                }

                TaxUnit::query()->create([
                    'unit_code' => $unitCode,
                    'unit_name' => $unitName,
                ]);
                $created++;
                ImportSocketNotifier::notify(
                    $user->id,
                    ImportSocketTopics::EXCEL_ROW_SUCCESS,
                    $importJob->id,
                    [
                        'row' => $excelRow,
                        'entity' => 'tax-unit',
                        'unitCode' => $unitCode,
                        'unitName' => $unitName,
                    ],
                );
            }

            $result = [
                'imported' => $created,
                'duplicates' => $duplicates,
                'updated' => 0,
                'failed' => $skipped,
                'errors' => [],
                'rows' => count($rows),
                'created' => $created,
                'skipped' => $duplicates + $skipped,
            ];

            $importJob->markCompleted($result);
            ImportSocketNotifier::notify(
                $user->id,
                ImportSocketTopics::EXCEL_COMPLETED,
                $importJob->id,
                [
                    'status' => TaxImportJob::STATUS_COMPLETED,
                    'result' => $result,
                    'message' => "Import đơn vị thuế hoàn tất: {$created} mới, {$duplicates} trùng bỏ qua, {$skipped} lỗi dữ liệu.",
                    'entity' => 'tax-unit',
                ],
            );
        } catch (\Throwable $exception) {
            $this->failJob($importJob, $user->id, $exception->getMessage());
        } finally {
            $disk->delete($importJob->file_path);
        }
    }

    private function failJob(TaxImportJob $importJob, int $userId, string $message): void
    {
        $importJob->markFailed($message);
        ImportSocketNotifier::notify(
            $userId,
            ImportSocketTopics::EXCEL_FAILED,
            $importJob->id,
            [
                'status' => TaxImportJob::STATUS_FAILED,
                'message' => $message,
                'entity' => 'tax-unit',
            ],
        );
    }
}
