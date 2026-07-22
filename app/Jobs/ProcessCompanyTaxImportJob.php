<?php

namespace App\Jobs;

use App\Imports\CompanyTaxColumnImport;
use App\Models\CompanyTaxManagement;
use App\Models\CompanyTaxPaymentHistory;
use App\Models\DoanhNghiep;
use App\Models\TaxImportJob;
use App\Models\TaxImportJobRow;
use App\Models\TaxUnit;
use App\Models\User;
use App\Support\ImportSocketNotifier;
use App\Support\ImportSocketTopics;
use App\Support\TaxImportColumnMap;
use App\Support\TaxImportRowRecorder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ProcessCompanyTaxImportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;
    public int $tries = 1;

    public function __construct(
        public readonly int $importJobId,
    ) {
        $this->onQueue('doanh-nghiep');
    }

    public function uniqueId(): string
    {
        return 'company-tax-import-' . $this->importJobId;
    }

    public function handle(): void
    {
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
                'entity' => 'company-tax',
            ],
        );

        $disk = Storage::disk('local');
        $absolutePath = $disk->path($importJob->file_path);
        if (!is_file($absolutePath)) {
            $this->failJob($importJob, $user->id, 'File import không tồn tại.');

            return;
        }

        $recorder = new TaxImportRowRecorder($importJob->id, $user->id);

        try {
            CompanyTaxManagement::query()->update(['is_active' => false]);

            $startRow = $importJob->start_row ?? TaxImportColumnMap::DEFAULT_START_ROW;
            $columnMap = $importJob->column_map;

            $imported = 0;
            $duplicates = 0;
            $failed = 0;
            $paidAt = $importJob->tax_paid_at?->toDateString() ?? now()->toDateString();

            /** @var array<string, true> $seenInFile */
            $seenInFile = [];

            $import = new CompanyTaxColumnImport(
                $startRow,
                $columnMap,
                function (array $row, int $excelRow) use (
                    $user,
                    $importJob,
                    $paidAt,
                    $recorder,
                    &$imported,
                    &$duplicates,
                    &$failed,
                    &$seenInFile
                ): void {
                    $taxCode = trim((string) ($row['taxCode'] ?? ''));
                    $taxUnitCode = trim((string) ($row['taxUnitCode'] ?? ''));

                    if ($taxCode === '' || $taxUnitCode === '') {
                        $recorder->record(
                            $excelRow,
                            TaxImportJobRow::STATUS_FAILED,
                            $taxCode ?: null,
                            null,
                            $taxUnitCode ?: null,
                            null,
                            null,
                            $this->taxRowLogMessage('Thiếu mã số thuế hoặc ID đơn vị thuế', $taxCode, $taxUnitCode),
                            $row,
                        );
                        $failed++;

                        return;
                    }

                    $company = DoanhNghiep::query()->where('ma_so_doanh_nghiep', $taxCode)->first();
                    $taxUnit = $this->resolveTaxUnit($taxUnitCode);

                    if (!$company) {
                        $recorder->record(
                            $excelRow,
                            TaxImportJobRow::STATUS_FAILED,
                            $taxCode,
                            null,
                            $taxUnitCode,
                            null,
                            $taxUnit?->id,
                            $this->taxRowLogMessage('Không tìm thấy doanh nghiệp', $taxCode, $taxUnitCode),
                            $row,
                        );
                        $failed++;

                        return;
                    }

                    if (!$taxUnit) {
                        $recorder->record(
                            $excelRow,
                            TaxImportJobRow::STATUS_FAILED,
                            $taxCode,
                            $company->ten_doanh_nghiep,
                            $taxUnitCode,
                            $company->id,
                            null,
                            $this->taxRowLogMessage('Không tìm thấy đơn vị thuế', $taxCode, $taxUnitCode),
                            $row,
                        );
                        $failed++;

                        return;
                    }

                    $dedupeKey = "{$company->id}:{$taxUnit->id}:{$paidAt}";
                    if (isset($seenInFile[$dedupeKey])) {
                        $recorder->record(
                            $excelRow,
                            TaxImportJobRow::STATUS_DUPLICATE,
                            $taxCode,
                            $company->ten_doanh_nghiep,
                            $taxUnitCode,
                            $company->id,
                            $taxUnit->id,
                            'Trùng dòng trong file import.',
                            $row,
                        );
                        $duplicates++;

                        return;
                    }

                    $seenInFile[$dedupeKey] = true;

                    $alreadyPaid = CompanyTaxPaymentHistory::query()
                        ->where('doanh_nghiep_id', $company->id)
                        ->where('tax_unit_id', $taxUnit->id)
                        ->whereDate('tax_paid_at', $paidAt)
                        ->exists();

                    if ($alreadyPaid) {
                        CompanyTaxManagement::query()
                            ->where('doanh_nghiep_id', $company->id)
                            ->update(['is_active' => true]);

                        $recorder->record(
                            $excelRow,
                            TaxImportJobRow::STATUS_DUPLICATE,
                            $taxCode,
                            $company->ten_doanh_nghiep,
                            $taxUnitCode,
                            $company->id,
                            $taxUnit->id,
                            'Đã có bản ghi đóng thuế cùng ngày.',
                            $row,
                        );
                        $duplicates++;

                        return;
                    }

                    CompanyTaxManagement::query()->updateOrCreate(
                        ['doanh_nghiep_id' => $company->id],
                        [
                            'tax_code' => $taxCode,
                            'tax_unit_id' => $taxUnit->id,
                            'tax_paid_at' => $paidAt,
                            'imported_by_user_id' => $user->id,
                            'is_active' => true,
                        ],
                    );
                    CompanyTaxPaymentHistory::query()->create([
                        'doanh_nghiep_id' => $company->id,
                        'tax_unit_id' => $taxUnit->id,
                        'tax_code' => $taxCode,
                        'tax_paid_at' => $paidAt,
                        'imported_by_user_id' => $user->id,
                        'source' => 'import',
                    ]);

                    $imported++;

                    $recorder->record(
                        $excelRow,
                        TaxImportJobRow::STATUS_SUCCESS,
                        $taxCode,
                        $company->ten_doanh_nghiep,
                        $taxUnitCode,
                        $company->id,
                        $taxUnit->id,
                        'Đã tạo bản ghi đóng thuế.',
                        $row,
                    );
                },
            );
            Excel::import($import, $absolutePath);
            $recorder->flush();

            $result = [
                'imported' => $imported,
                'duplicates' => $duplicates,
                'failed' => $failed,
                'rows' => $import->processedRows(),
            ];

            $importJob->markCompleted($result);
            ImportSocketNotifier::notify(
                $user->id,
                ImportSocketTopics::EXCEL_COMPLETED,
                $importJob->id,
                [
                    'status' => TaxImportJob::STATUS_COMPLETED,
                    'result' => $result,
                    'message' => "Import doanh nghiệp đóng thuế hoàn tất: {$imported} thành công, {$duplicates} trùng, {$failed} thất bại.",
                    'entity' => 'company-tax',
                ],
            );
        } catch (\Throwable $exception) {
            $recorder->flush();
            $this->failJob($importJob, $user->id, $exception->getMessage());
        } finally {
            $disk->delete($importJob->file_path);
        }
    }

    private function taxRowLogMessage(string $message, string $taxCode, string $taxUnitCode): string
    {
        $mst = $taxCode !== '' ? $taxCode : 'trống';
        $taxUnitId = $taxUnitCode !== '' ? $taxUnitCode : 'trống';

        return "{$message} — MST: {$mst}, ID thuế: {$taxUnitId}";
    }

    private function resolveTaxUnit(string $code): ?TaxUnit
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $byCode = TaxUnit::query()->where('unit_code', $code)->first();
        if ($byCode) {
            return $byCode;
        }

        if (ctype_digit($code)) {
            return TaxUnit::query()->find((int) $code);
        }

        return null;
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
                'entity' => 'company-tax',
            ],
        );
    }
}
