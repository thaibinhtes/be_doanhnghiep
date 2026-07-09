<?php

namespace App\Http\Controllers\Api;

use App\Jobs\ProcessCompanyTaxImportJob;
use App\Jobs\ProcessCooperativeTaxImportJob;
use App\Models\CompanyTaxManagement;
use App\Models\CompanyTaxPaymentHistory;
use App\Models\CooperativeTaxManagement;
use App\Models\DoanhNghiep;
use App\Models\HopTacXa;
use App\Models\TaxImportJob;
use App\Models\TaxUnit;
use App\Support\ImportJobScopeHelper;
use App\Support\TaxExcelColumns;
use App\Support\TaxImportColumnMap;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaxManagementController extends ApiController
{
    public function importJobs(Request $request): JsonResponse
    {
        $type = (string) $request->query('type', '');
        $allowedTypes = [TaxImportJob::TYPE_TAX_UNITS, TaxImportJob::TYPE_COMPANY_TAX, TaxImportJob::TYPE_COOPERATIVE_TAX];
        $perPage = min(max((int) $request->query('perPage', $request->query('per_page', 20)), 1), 100);

        $query = ImportJobScopeHelper::applyScope(
            TaxImportJob::query()
                ->with(['user:id,name', 'donVi:id,ten,ma'])
                ->orderByDesc('created_at'),
            $request->user(),
        );

        if (in_array($type, $allowedTypes, true)) {
            $query->where('type', $type);
        }

        $items = $query->paginate($perPage);

        return $this->success([
            'data' => $items->getCollection()->map(function (TaxImportJob $item) {
                $result = is_array($item->result) ? $item->result : [];
                $imported = (int) ($result['imported'] ?? 0);
                $duplicates = (int) ($result['duplicates'] ?? $result['updated'] ?? 0);
                $failed = (int) ($result['failed'] ?? 0);
                return [
                    'id' => $item->id,
                    'type' => $item->type,
                    'status' => $item->status,
                    'originalFilename' => $item->original_filename,
                    'result' => $result,
                    'summary' => [
                        'imported' => $imported,
                        'duplicates' => $duplicates,
                        'failed' => $failed,
                    ],
                    'errorMessage' => $item->error_message,
                    'importedBy' => $item->user ? [
                        'id' => $item->user->id,
                        'name' => $item->user->name,
                    ] : null,
                    'donVi' => $item->donVi ? [
                        'id' => $item->donVi->id,
                        'ten' => $item->donVi->ten,
                        'ma' => $item->donVi->ma,
                    ] : null,
                    'createdAt' => $item->created_at?->toIso8601String(),
                    'startedAt' => $item->started_at?->toIso8601String(),
                    'finishedAt' => $item->finished_at?->toIso8601String(),
                ];
            })->values(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ], 'Lấy lịch sử import thuế thành công');
    }

    public function companyList(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $fromDate = $this->parseDateOrNull($request->query('paidFrom'));
        $toDate = $this->parseDateOrNull($request->query('paidTo'));
        $activeOnly = $request->boolean('activeOnly');

        $query = CompanyTaxManagement::query()
            ->with(['doanhNghiep', 'taxUnit', 'importedBy'])
            ->orderByDesc('created_at');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('tax_code', 'like', "%{$search}%")
                    ->orWhereHas('doanhNghiep', function ($companyQuery) use ($search) {
                        $companyQuery
                            ->where('ten_doanh_nghiep', 'like', "%{$search}%")
                            ->orWhere('ma_so_doanh_nghiep', 'like', "%{$search}%");
                    });
            });
        }

        if ($fromDate) {
            $query->whereDate('tax_paid_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('tax_paid_at', '<=', $toDate);
        }

        $perPage = min(max((int) $request->query('perPage', $request->query('per_page', 50)), 1), 200);
        $items = $query->paginate($perPage);

        $data = $items->getCollection()->map(function (CompanyTaxManagement $item) {
            return [
                'id' => $item->doanh_nghiep_id,
                'taxCode' => $item->tax_code ?: $item->doanhNghiep?->ma_so_doanh_nghiep,
                'companyName' => $item->doanhNghiep?->ten_doanh_nghiep,
                'taxUnitId' => $item->tax_unit_id,
                'taxUnit' => $item->taxUnit ? [
                    'id' => $item->taxUnit->id,
                    'unitCode' => $item->taxUnit->unit_code,
                    'unitName' => $item->taxUnit->unit_name,
                ] : null,
                'taxPaidAt' => $item->tax_paid_at?->toDateString(),
                'isActive' => (bool) $item->is_active,
                'createdAt' => $item->created_at?->toIso8601String(),
                'importedBy' => $item->importedBy ? [
                    'id' => $item->importedBy->id,
                    'name' => $item->importedBy->name,
                ] : null,
            ];
        })->values();

        return $this->success([
            'data' => $data,
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ], 'Lấy danh sách doanh nghiệp đóng thuế thành công');
    }

    public function cooperativeList(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $fromDate = $this->parseDateOrNull($request->query('paidFrom'));
        $toDate = $this->parseDateOrNull($request->query('paidTo'));
        $activeOnly = $request->boolean('activeOnly');

        $query = CooperativeTaxManagement::query()
            ->with(['hopTacXa', 'taxUnit', 'importedBy'])
            ->orderByDesc('created_at');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('tax_code', 'like', "%{$search}%")
                    ->orWhereHas('hopTacXa', function ($cooperativeQuery) use ($search) {
                        $cooperativeQuery
                            ->where('ten_htx', 'like', "%{$search}%")
                            ->orWhere('ma_so_thue', 'like', "%{$search}%");
                    });
            });
        }

        if ($fromDate) {
            $query->whereDate('tax_paid_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('tax_paid_at', '<=', $toDate);
        }

        $perPage = min(max((int) $request->query('perPage', $request->query('per_page', 50)), 1), 200);
        $items = $query->paginate($perPage);

        $data = $items->getCollection()->map(function (CooperativeTaxManagement $item) {
            return [
                'id' => $item->hop_tac_xa_id,
                'taxCode' => $item->tax_code ?: $item->hopTacXa?->ma_so_thue,
                'cooperativeName' => $item->hopTacXa?->ten_htx,
                'taxUnitId' => $item->tax_unit_id,
                'taxUnit' => $item->taxUnit ? [
                    'id' => $item->taxUnit->id,
                    'unitCode' => $item->taxUnit->unit_code,
                    'unitName' => $item->taxUnit->unit_name,
                ] : null,
                'taxPaidAt' => $item->tax_paid_at?->toDateString(),
                'isActive' => (bool) $item->is_active,
                'createdAt' => $item->created_at?->toIso8601String(),
                'importedBy' => $item->importedBy ? [
                    'id' => $item->importedBy->id,
                    'name' => $item->importedBy->name,
                ] : null,
            ];
        })->values();

        return $this->success([
            'data' => $data,
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ], 'Lấy danh sách HTX đóng thuế thành công');
    }

    public function upsertCompany(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'doanhNghiepId' => ['required', 'integer', 'exists:doanh_nghieps,id'],
            'taxUnitId' => ['nullable', 'integer', 'exists:tax_units,id'],
            'taxPaidAt' => ['nullable', 'date'],
        ]);

        $company = DoanhNghiep::query()->findOrFail((int) $payload['doanhNghiepId']);

        if (empty($payload['taxUnitId'])) {
            CompanyTaxManagement::query()->where('doanh_nghiep_id', $company->id)->delete();

            return $this->success(null, 'Đã bỏ đơn vị thuế cho doanh nghiệp');
        }

        $taxPaidAt = $this->parseDateOrNull($payload['taxPaidAt'] ?? null) ?? now()->toDateString();

        CompanyTaxManagement::query()->updateOrCreate(
            ['doanh_nghiep_id' => $company->id],
            [
                'tax_code' => (string) ($company->ma_so_doanh_nghiep ?? ''),
                'tax_unit_id' => (int) $payload['taxUnitId'],
                'tax_paid_at' => $taxPaidAt,
                'imported_by_user_id' => (int) $request->user()->id,
                'is_active' => true,
            ],
        );
        CompanyTaxPaymentHistory::query()->create([
            'doanh_nghiep_id' => $company->id,
            'tax_unit_id' => (int) $payload['taxUnitId'],
            'tax_code' => (string) ($company->ma_so_doanh_nghiep ?? ''),
            'tax_paid_at' => $taxPaidAt,
            'imported_by_user_id' => (int) $request->user()->id,
            'source' => 'manual',
        ]);

        return $this->success(null, 'Cập nhật đơn vị thuế doanh nghiệp thành công');
    }

    public function companyPaymentHistory(DoanhNghiep $doanhNghiep, Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('perPage', $request->query('per_page', 20)), 1), 200);
        $items = CompanyTaxPaymentHistory::query()
            ->with(['taxUnit', 'importedBy'])
            ->where('doanh_nghiep_id', $doanhNghiep->id)
            ->orderByDesc('tax_paid_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->success([
            'data' => $items->getCollection()->map(function (CompanyTaxPaymentHistory $item) {
                return [
                    'id' => $item->id,
                    'taxCode' => $item->tax_code,
                    'taxPaidAt' => $item->tax_paid_at?->toDateString(),
                    'source' => $item->source,
                    'taxUnit' => $item->taxUnit ? [
                        'id' => $item->taxUnit->id,
                        'unitCode' => $item->taxUnit->unit_code,
                        'unitName' => $item->taxUnit->unit_name,
                    ] : null,
                    'importedBy' => $item->importedBy ? [
                        'id' => $item->importedBy->id,
                        'name' => $item->importedBy->name,
                    ] : null,
                    'createdAt' => $item->created_at?->toIso8601String(),
                ];
            })->values(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ], 'Lấy lịch sử đóng thuế thành công');
    }

    public function companiesByTaxUnit(TaxUnit $taxUnit, Request $request): JsonResponse
    {
        $fromDate = $this->parseDateOrNull($request->query('paidFrom'));
        $toDate = $this->parseDateOrNull($request->query('paidTo'));

        $query = CompanyTaxManagement::query()
            ->with(['doanhNghiep', 'importedBy'])
            ->where('tax_unit_id', $taxUnit->id);

        if ($fromDate) {
            $query->whereDate('tax_paid_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('tax_paid_at', '<=', $toDate);
        }

        $perPage = min(max((int) $request->query('perPage', $request->query('per_page', 50)), 1), 200);
        $items = $query->orderByDesc('tax_paid_at')->paginate($perPage);

        return $this->success([
            'data' => $items->getCollection()->map(function (CompanyTaxManagement $item) {
                return [
                    'id' => $item->doanh_nghiep_id,
                    'taxCode' => $item->tax_code,
                    'companyName' => $item->doanhNghiep?->ten_doanh_nghiep,
                    'taxPaidAt' => $item->tax_paid_at?->toDateString(),
                    'importedBy' => $item->importedBy ? [
                        'id' => $item->importedBy->id,
                        'name' => $item->importedBy->name,
                    ] : null,
                ];
            })->values(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ], 'Lấy danh sách doanh nghiệp đóng thuế theo đơn vị thành công');
    }

    public function upsertCooperative(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'hopTacXaId' => ['required', 'integer', 'exists:hop_tac_xas,id'],
            'taxUnitId' => ['nullable', 'integer', 'exists:tax_units,id'],
        ]);

        $item = HopTacXa::query()->findOrFail((int) $payload['hopTacXaId']);

        if (empty($payload['taxUnitId'])) {
            CooperativeTaxManagement::query()->where('hop_tac_xa_id', $item->id)->delete();

            return $this->success(null, 'Đã bỏ đơn vị thuế cho HTX');
        }

        CooperativeTaxManagement::query()->updateOrCreate(
            ['hop_tac_xa_id' => $item->id],
            [
                'tax_code' => (string) ($item->ma_so_thue ?? ''),
                'tax_unit_id' => (int) $payload['taxUnitId'],
            ],
        );

        return $this->success(null, 'Cập nhật đơn vị thuế HTX thành công');
    }

    public function companyImportColumnMap(): JsonResponse
    {
        return $this->success([
            'startRow' => TaxImportColumnMap::DEFAULT_START_ROW,
            'columnMap' => TaxImportColumnMap::COMPANY_TAX_COLUMN_MAP,
            'columnLabels' => TaxExcelColumns::companyTaxColumnLabels(),
            'valueExtensions' => [],
        ]);
    }

    public function importCompanyExcel(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
            'startRow' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'columnMap' => ['nullable'],
            'taxPaidAt' => ['nullable', 'date'],
        ]);

        $startRow = $request->has('startRow') ? (int) $request->input('startRow') : TaxImportColumnMap::DEFAULT_START_ROW;
        $columnMap = $this->parseImportColumnMap($request->input('columnMap'));
        $taxPaidAt = $this->parseDateOrNull($request->input('taxPaidAt')) ?? now()->toDateString();

        $uploadedFile = $request->file('file');
        $storedPath = $uploadedFile->store('imports/pending');

        $importJob = TaxImportJob::query()->create([
            'user_id' => (int) $request->user()->id,
            'don_vi_id' => ImportJobScopeHelper::resolveDonViId($request->user()),
            'status' => TaxImportJob::STATUS_PENDING,
            'type' => TaxImportJob::TYPE_COMPANY_TAX,
            'file_path' => $storedPath,
            'original_filename' => $uploadedFile->getClientOriginalName(),
            'start_row' => $startRow,
            'tax_paid_at' => $taxPaidAt,
            'column_map' => $columnMap,
        ]);

        ProcessCompanyTaxImportJob::dispatch($importJob->id);

        return $this->success([
            'importJobId' => $importJob->id,
            'status' => $importJob->status,
            'originalFilename' => $importJob->original_filename,
            'entity' => 'company-tax',
        ], 'Đã đưa file import doanh nghiệp đóng thuế vào hàng đợi.', 202);
    }

    public function cooperativeImportColumnMap(): JsonResponse
    {
        return $this->success([
            'startRow' => TaxImportColumnMap::DEFAULT_START_ROW,
            'columnMap' => TaxImportColumnMap::COOPERATIVE_TAX_COLUMN_MAP,
            'columnLabels' => TaxExcelColumns::cooperativeTaxColumnLabels(),
            'valueExtensions' => [],
        ]);
    }

    public function importCooperativeExcel(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
            'startRow' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'columnMap' => ['nullable'],
            'taxPaidAt' => ['nullable', 'date'],
        ]);

        $startRow = $request->has('startRow') ? (int) $request->input('startRow') : TaxImportColumnMap::DEFAULT_START_ROW;
        $columnMap = $this->parseImportColumnMap($request->input('columnMap'));
        $taxPaidAt = $this->parseDateOrNull($request->input('taxPaidAt')) ?? now()->toDateString();

        $uploadedFile = $request->file('file');
        $storedPath = $uploadedFile->store('imports/pending');

        $importJob = TaxImportJob::query()->create([
            'user_id' => (int) $request->user()->id,
            'don_vi_id' => ImportJobScopeHelper::resolveDonViId($request->user()),
            'status' => TaxImportJob::STATUS_PENDING,
            'type' => TaxImportJob::TYPE_COOPERATIVE_TAX,
            'file_path' => $storedPath,
            'original_filename' => $uploadedFile->getClientOriginalName(),
            'start_row' => $startRow,
            'tax_paid_at' => $taxPaidAt,
            'column_map' => $columnMap,
        ]);

        ProcessCooperativeTaxImportJob::dispatch($importJob->id);

        return $this->success([
            'importJobId' => $importJob->id,
            'status' => $importJob->status,
            'originalFilename' => $importJob->original_filename,
            'entity' => 'cooperative-tax',
        ], 'Đã đưa file import hợp tác xã đóng thuế vào hàng đợi.', 202);
    }

    private function parseDateOrNull(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, list<string>>|null
     */
    private function parseImportColumnMap(mixed $input): ?array
    {
        if ($input === null || $input === '') {
            return null;
        }

        if (is_string($input)) {
            $decoded = json_decode($input, true);

            return is_array($decoded) ? $decoded : null;
        }

        return is_array($input) ? $input : null;
    }
}
