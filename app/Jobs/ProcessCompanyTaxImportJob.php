<?php

namespace App\Jobs;

use App\Imports\CompanyTaxColumnImport;
use App\Models\CompanyTaxManagement;
use App\Models\CompanyTaxPaymentHistory;
use App\Models\DoanhNghiep;
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

class ProcessCompanyTaxImportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;
    public int $tries = 1;

    public function __construct(
        public readonly int $importJobId,
    ) {}

    public function uniqueId(): string
    {
        return 'company-tax-import-' . $this->importJobId;
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
                'entity' => 'company-tax',
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

            $created = 0;
            $updated = 0;
            $skipped = 0;
            $paidAt = $importJob->tax_paid_at?->toDateString() ?? now()->toDateString();

            $import = new CompanyTaxColumnImport(
                $startRow,
                $columnMap,
                function (array $row, int $excelRow) use (
                    $user,
                    $importJob,
                    $paidAt,
                    &$created,
                    &$updated,
                    &$skipped
                ): void {
                $taxCode = trim((string) ($row['taxCode'] ?? ''));
                $taxUnitCode = trim((string) ($row['taxUnitCode'] ?? ''));

                if ($taxCode === '' || $taxUnitCode === '') {
                    ImportSocketNotifier::notify(
                        $user->id,
                        ImportSocketTopics::EXCEL_ROW_FAILED,
                        $importJob->id,
                        [
                            'row' => $excelRow,
                            'entity' => 'company-tax',
                            'maSoDoanhNghiep' => $taxCode ?: null,
                            'message' => 'Thiếu mã số thuế hoặc mã đơn vị thuế.',
                        ],
                    );

                    $skipped++;
                    return;
                }

                $company = DoanhNghiep::query()->where('ma_so_doanh_nghiep', $taxCode)->first();
                $taxUnit = TaxUnit::query()->where('unit_code', $taxUnitCode)->first();

                if (!$company || !$taxUnit) {
                    ImportSocketNotifier::notify(
                        $user->id,
                        ImportSocketTopics::EXCEL_ROW_FAILED,
                        $importJob->id,
                        [
                            'row' => $excelRow,
                            'entity' => 'company-tax',
                            'maSoDoanhNghiep' => $taxCode,
                            'message' => 'Không tìm thấy doanh nghiệp hoặc đơn vị thuế.',
                        ],
                    );

                    $skipped++;
                    return;
                }

                $existing = CompanyTaxManagement::query()->where('doanh_nghiep_id', $company->id)->exists();
                CompanyTaxManagement::query()->updateOrCreate(
                    ['doanh_nghiep_id' => $company->id],
                    [
                        'tax_code' => $taxCode,
                        'tax_unit_id' => $taxUnit->id,
                        'tax_paid_at' => $paidAt,
                        'imported_by_user_id' => $user->id,
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

                $this->syncCompanyOperatingStatus($company->id);
                $existing ? $updated++ : $created++;

                ImportSocketNotifier::notify(
                    $user->id,
                    ImportSocketTopics::EXCEL_ROW_SUCCESS,
                    $importJob->id,
                    [
                        'row' => $excelRow,
                        'entity' => 'company-tax',
                        'maSoDoanhNghiep' => $taxCode,
                        'tenDoanhNghiep' => $company->ten_doanh_nghiep,
                        'message' => $existing ? 'Đã cập nhật đơn vị thuế.' : 'Đã thêm doanh nghiệp đóng thuế.',
                    ],
                );
                },
            );
            Excel::import($import, $absolutePath);

            $result = [
                'imported' => $created,
                'duplicates' => $updated,
                'updated' => $updated,
                'failed' => $skipped,
                'errors' => [],
                'rows' => $import->processedRows(),
                'created' => $created,
                'skipped' => $skipped,
            ];

            $importJob->markCompleted($result);
            ImportSocketNotifier::notify(
                $user->id,
                ImportSocketTopics::EXCEL_COMPLETED,
                $importJob->id,
                [
                    'status' => TaxImportJob::STATUS_COMPLETED,
                    'result' => $result,
                    'message' => "Import doanh nghiệp đóng thuế hoàn tất: {$created} mới, {$updated} cập nhật, {$skipped} bỏ qua.",
                    'entity' => 'company-tax',
                ],
            );
        } catch (\Throwable $exception) {
            $this->failJob($importJob, $user->id, $exception->getMessage());
        } finally {
            $disk->delete($importJob->file_path);
        }
    }

    private function syncCompanyOperatingStatus(int $companyId): void
    {
        $hasTaxRecord = CompanyTaxManagement::query()
            ->where('doanh_nghiep_id', $companyId)
            ->exists();

        DoanhNghiep::query()
            ->where('id', $companyId)
            ->update([
                'trang_thai' => $hasTaxRecord ? 'Hoạt động' : 'Không hoạt động',
            ]);
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
