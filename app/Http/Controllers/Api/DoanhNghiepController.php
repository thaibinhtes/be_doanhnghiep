<?php

namespace App\Http\Controllers\Api;

use App\Exports\DoanhNghiepExport;
use App\Exports\DoanhNghiepDinhDanhTemplateExport;
use App\Exports\DoanhNghiepTemplateExport;
use App\Http\Requests\Api\StoreDoanhNghiepRequest;
use App\Http\Requests\Api\UpdateDoanhNghiepRequest;
use App\Http\Resources\DnDinhDanhLichSuResource;
use App\Http\Resources\DoanhNghiepResource;
use App\Jobs\ProcessDoanhNghiepFieldUpdateImportJob;
use App\Jobs\ProcessDoanhNghiepImportJob;
use App\Jobs\ProcessDoanhNghiepIdentityImportJob;
use App\Models\DoanhNghiep;
use App\Models\DoanhNghiepImportJob;
use App\Models\DonVi;
use App\Models\Member;
use App\Models\User;
use App\Support\DinhDanhHistoryContext;
use App\Support\DoanhNghiepDinhDanhImportColumnMap;
use App\Support\DoanhNghiepExcelColumns;
use App\Support\DoanhNghiepFieldUpdateImportColumnMap;
use App\Support\DoanhNghiepFieldUpdateRegistry;
use App\Support\DoanhNghiepImportColumnMap;
use InvalidArgumentException;
use App\Support\ImportUploadLogger;
use App\Support\ImportUploadValidator;
use App\Support\DoanhNghiepImportExtensionHelper;
use App\Support\DoanhNghiepLoaiHinhHelper;
use App\Support\DoanhNghiepNganhNgheHelper;
use App\Support\DoanhNghiepScopeHelper;
use App\Support\DonViDataClearService;
use App\Support\DoanhNghiepStatusHelper;
use App\Support\ImportExcelKindDetector;
use App\Support\ImportExcelKindGuard;
use App\Support\ImportJobScopeHelper;
use App\Support\ImportSocketNotifier;
use App\Support\ImportSocketTopics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DoanhNghiepController extends ApiController
{
    private const DINH_DANH_LABEL_UPDATED = 'Đã cập nhật định danh';
    private const DINH_DANH_LABEL_PENDING = 'Chưa cập nhật định danh';

    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        $perPage = min(max((int) request('per_page', request('perPage', 50)), 1), 500);
        $doanhNghieps = $this->buildFilteredQuery()->paginate($perPage);

        return DoanhNghiepResource::collection($doanhNghieps);
    }

    /**
     * Export filtered companies to Excel.
     */
    public function export(): BinaryFileResponse
    {
        $filename = 'doanh-nghiep_' . now()->format('Y-m-d_His') . '.xlsx';

        $query = $this->buildFilteredQuery();
        if (!request('sortBy')) {
            $query->reorder()->orderByRaw('tt IS NULL')->orderBy('tt')->orderBy('id');
        }

        return Excel::download(
            new DoanhNghiepExport($query),
            $filename
        );
    }

    /**
     * Download empty Excel template for import.
     */
    public function exportTemplate(): BinaryFileResponse
    {
        return Excel::download(
            new DoanhNghiepTemplateExport(),
            'mau-import-doanh-nghiep.xlsx'
        );
    }

    /**
     * Download identity import template.
     */
    public function exportIdentityTemplate(): BinaryFileResponse
    {
        return Excel::download(
            new DoanhNghiepDinhDanhTemplateExport(),
            'mau-import-dinh-danh-doanh-nghiep.xlsx'
        );
    }

    /**
     * Default column mapping for unit-provided Excel templates.
     */
    public function importColumnMap(): JsonResponse
    {
        return $this->success([
            'startRow' => DoanhNghiepImportColumnMap::DEFAULT_START_ROW,
            'columnMap' => DoanhNghiepImportColumnMap::UNIT_TEMPLATE,
            'columnLabels' => DoanhNghiepExcelColumns::importColumnLabels(),
            'availableValueExtensions' => DoanhNghiepImportExtensionHelper::availableExtensions(),
            'valueExtensions' => [],
        ]);
    }

    /**
     * Queue import companies from Excel file.
     */
    public function import(): JsonResponse
    {
        ImportUploadValidator::validate(request(), 'doanh_nghiep_import', [
            'startRow' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'columnMap' => ['nullable'],
            'valueExtensions' => ['nullable'],
        ]);

        $user = request()->user();
        if ($response = $this->ensureUserHasDonViForAssignment($user)) {
            return $response;
        }

        $startRow = request()->has('startRow')
            ? (int) request('startRow')
            : DoanhNghiepImportColumnMap::DEFAULT_START_ROW;
        $columnMap = $this->parseImportColumnMap(request()->input('columnMap'));
        $valueExtensions = $this->parseImportValueExtensions(request()->input('valueExtensions'));
        ImportExcelKindGuard::assertDoanhNghiepColumnMap($columnMap);
        $useColumnMap = request()->has('startRow') || $columnMap !== null || $valueExtensions !== null;

        try {
            $uploadedFile = request()->file('file');
            $storedPath = $uploadedFile->store('imports/pending');
        } catch (\Throwable $e) {
            ImportUploadLogger::exception('doanh_nghiep_import', request(), $e, 'store_file');
            ImportUploadValidator::throwError(
                'Không lưu được file upload. Kiểm tra quyền thư mục storage/app/imports.',
                'store_failed',
            );
        }

        ImportExcelKindGuard::assertExpectedKind(
            Storage::disk('local')->path($storedPath),
            ImportExcelKindDetector::KIND_DOANH_NGHIEP,
        );

        $importJob = DoanhNghiepImportJob::query()->create([
            'user_id' => $user->id,
            'don_vi_id' => ImportJobScopeHelper::resolveDonViId($user),
            'status' => DoanhNghiepImportJob::STATUS_PENDING,
            'type' => DoanhNghiepImportJob::TYPE_COMPANIES,
            'file_path' => $storedPath,
            'original_filename' => $uploadedFile->getClientOriginalName(),
            'start_row' => $startRow,
            'column_map' => $columnMap,
            'value_extensions' => $valueExtensions,
            'use_column_map' => $useColumnMap,
        ]);

        ProcessDoanhNghiepImportJob::dispatch($importJob->id);

        ImportUploadLogger::succeeded('doanh_nghiep_import', request(), [
            'import_job_id' => $importJob->id,
            'stored_path' => $storedPath,
            'original_filename' => $importJob->original_filename,
        ]);

        ImportSocketNotifier::notify(
            $user->id,
            ImportSocketTopics::EXCEL_STARTED,
            $importJob->id,
            [
                'status' => DoanhNghiepImportJob::STATUS_PENDING,
                'originalFilename' => $importJob->original_filename,
                'entity' => 'doanh-nghiep',
            ],
        );

        return $this->success(
            [
                'importJobId' => $importJob->id,
                'status' => $importJob->status,
                'originalFilename' => $importJob->original_filename,
                'entity' => 'doanh-nghiep',
            ],
            'Đã đưa file import vào hàng đợi. Bạn sẽ nhận thông báo khi hoàn tất.',
            202,
        );
    }

    /**
     * Import identity status updates from Excel file.
     */
    public function importDinhDanh(): JsonResponse
    {
        ImportUploadValidator::validate(request(), 'doanh_nghiep_import_dinh_danh', [
            'startRow' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'columnMap' => ['nullable'],
            'daCapNhatDinhDanh' => ['required', 'boolean'],
        ]);

        $startRow = request()->has('startRow')
            ? (int) request('startRow')
            : DoanhNghiepDinhDanhImportColumnMap::DEFAULT_START_ROW;
        $columnMap = DoanhNghiepDinhDanhImportColumnMap::normalizeStoredColumnMap(
            $this->parseImportColumnMap(request()->input('columnMap')) ?? DoanhNghiepDinhDanhImportColumnMap::DEFAULT_COLUMN_MAP
        );
        $forcedDinhDanhStatus = filter_var(request()->input('daCapNhatDinhDanh'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        try {
            $uploadedFile = request()->file('file');
            $storedPath = $uploadedFile->store('imports/pending');
        } catch (\Throwable $e) {
            ImportUploadLogger::exception('doanh_nghiep_import_dinh_danh', request(), $e, 'store_file');
            ImportUploadValidator::throwError(
                'Không lưu được file upload. Kiểm tra quyền thư mục storage/app/imports.',
                'store_failed',
            );
        }

        $importJob = DoanhNghiepImportJob::query()->create([
            'user_id' => request()->user()->id,
            'don_vi_id' => ImportJobScopeHelper::resolveDonViId(request()->user()),
            'status' => DoanhNghiepImportJob::STATUS_PENDING,
            'type' => DoanhNghiepImportJob::TYPE_IDENTITIES,
            'file_path' => $storedPath,
            'original_filename' => $uploadedFile->getClientOriginalName(),
            'start_row' => $startRow,
            'column_map' => $columnMap,
            'use_column_map' => true,
        ]);
        ProcessDoanhNghiepIdentityImportJob::dispatch($importJob->id, (bool) $forcedDinhDanhStatus);

        ImportUploadLogger::succeeded('doanh_nghiep_import_dinh_danh', request(), [
            'import_job_id' => $importJob->id,
            'stored_path' => $storedPath,
            'original_filename' => $importJob->original_filename,
        ]);

        return $this->success(
            [
                'importJobId' => $importJob->id,
                'status' => $importJob->status,
                'originalFilename' => $importJob->original_filename,
                'entity' => 'doanh-nghiep',
            ],
            'Đã đưa file import định danh vào hàng đợi. Bạn sẽ nhận thông báo khi hoàn tất.',
            202
        );
    }

    /**
     * Default mapping for identity import.
     */
    public function importDinhDanhColumnMap(): JsonResponse
    {
        return $this->success([
            'startRow' => DoanhNghiepDinhDanhImportColumnMap::DEFAULT_START_ROW,
            'columnMap' => DoanhNghiepDinhDanhImportColumnMap::DEFAULT_COLUMN_MAP,
            'columnLabels' => [
                'maSoDoanhNghiep' => DoanhNghiepExcelColumns::COLUMNS['maSoDoanhNghiep'],
            ],
        ]);
    }

    /**
     * Whitelist + default mapping for bulk field updates from Excel.
     */
    public function importFieldUpdateColumnMap(): JsonResponse
    {
        $options = DoanhNghiepFieldUpdateRegistry::options();

        return $this->success([
            'startRow' => DoanhNghiepFieldUpdateImportColumnMap::DEFAULT_START_ROW,
            'lookupField' => DoanhNghiepFieldUpdateImportColumnMap::DEFAULT_LOOKUP_FIELD,
            'columnMap' => DoanhNghiepFieldUpdateImportColumnMap::DEFAULT_COLUMN_MAP,
            'lookupFields' => $options['lookupFields'],
            'updateFields' => $options['updateFields'],
            'columnLabels' => array_merge($options['lookupFields'], $options['updateFields']),
        ]);
    }

    /**
     * Queue bulk field updates from Excel (update-only, never create).
     */
    public function importFieldUpdates(): JsonResponse
    {
        ImportUploadValidator::validate(request(), 'doanh_nghiep_import_field_updates', [
            'startRow' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'lookupField' => ['required', 'string'],
            'columnMap' => ['required'],
        ]);

        $user = request()->user();
        if ($response = $this->ensureUserHasDonViForAssignment($user)) {
            return $response;
        }

        $lookupField = (string) request('lookupField');
        if (!DoanhNghiepFieldUpdateRegistry::isLookupField($lookupField)) {
            ImportUploadValidator::throwError('Trường đối chiếu không hợp lệ.', 'invalid_lookup_field');
        }

        $startRow = request()->has('startRow')
            ? (int) request('startRow')
            : DoanhNghiepFieldUpdateImportColumnMap::DEFAULT_START_ROW;

        $columnMap = DoanhNghiepFieldUpdateImportColumnMap::normalizeStoredColumnMap(
            $this->parseImportColumnMap(request()->input('columnMap')) ?? []
        );

        try {
            DoanhNghiepFieldUpdateImportColumnMap::assertValid($columnMap, $lookupField);
        } catch (InvalidArgumentException $exception) {
            ImportUploadValidator::throwError($exception->getMessage(), 'invalid_column_map');
        }

        try {
            $uploadedFile = request()->file('file');
            $storedPath = $uploadedFile->store('imports/pending');
        } catch (\Throwable $e) {
            ImportUploadLogger::exception('doanh_nghiep_import_field_updates', request(), $e, 'store_file');
            ImportUploadValidator::throwError(
                'Không lưu được file upload. Kiểm tra quyền thư mục storage/app/imports.',
                'store_failed',
            );
        }

        $importJob = DoanhNghiepImportJob::query()->create([
            'user_id' => $user->id,
            'don_vi_id' => ImportJobScopeHelper::resolveDonViId($user),
            'status' => DoanhNghiepImportJob::STATUS_PENDING,
            'type' => DoanhNghiepImportJob::TYPE_FIELD_UPDATES,
            'file_path' => $storedPath,
            'original_filename' => $uploadedFile->getClientOriginalName(),
            'start_row' => $startRow,
            'column_map' => $columnMap,
            'value_extensions' => ['lookupField' => $lookupField],
            'use_column_map' => true,
        ]);

        ProcessDoanhNghiepFieldUpdateImportJob::dispatch($importJob->id);

        ImportUploadLogger::succeeded('doanh_nghiep_import_field_updates', request(), [
            'import_job_id' => $importJob->id,
            'stored_path' => $storedPath,
            'original_filename' => $importJob->original_filename,
        ]);

        ImportSocketNotifier::notify(
            $user->id,
            ImportSocketTopics::EXCEL_STARTED,
            $importJob->id,
            [
                'status' => DoanhNghiepImportJob::STATUS_PENDING,
                'originalFilename' => $importJob->original_filename,
                'entity' => 'doanh-nghiep',
            ],
        );

        return $this->success(
            [
                'importJobId' => $importJob->id,
                'status' => $importJob->status,
                'originalFilename' => $importJob->original_filename,
                'entity' => 'doanh-nghiep',
            ],
            'Đã đưa file cập nhật field vào hàng đợi. Bạn sẽ nhận thông báo khi hoàn tất.',
            202,
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDoanhNghiepRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $danhSachTV = $validated['danhSachThanhVienGopVon'] ?? [];
        unset($validated['danhSachThanhVienGopVon']);

        $data = $this->mapCamelToSnake($validated);
        $data = DoanhNghiepStatusHelper::applyStatus($data);
        $data = DoanhNghiepLoaiHinhHelper::applyLoaiHinh($data);
        $data = DoanhNghiepNganhNgheHelper::apply($data);

        $user = $request->user();
        if ($response = $this->ensureUserHasDonViForAssignment($user)) {
            return $response;
        }

        if ($user) {
            $data['don_vi_id'] = DoanhNghiepScopeHelper::resolveAssignmentDonViId($user);
            $data['created_by_user_id'] = $user->id;
        }

        $doanhNghiep = DinhDanhHistoryContext::run(['nguon' => 'tao_moi'], function () use ($data, $danhSachTV) {
            $company = DoanhNghiep::create($data);

            if (!empty($danhSachTV)) {
                $this->syncMembersToCompany($company, $danhSachTV);
            }

            return $company;
        });

        $doanhNghiep->load(['chuSoHuu', 'nguoiDaiDien', 'memberCompanies.member', 'dnTrangThai', 'dnLoaiHinh', 'nganhNgheKdChinh', 'donVi', 'createdByUser']);

        return $this->success(
            new DoanhNghiepResource($doanhNghiep),
            'Doanh nghiệp created successfully',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(DoanhNghiep $doanhNghiep): JsonResponse
    {
        if (!$this->userCanAccessCompany($doanhNghiep)) {
            return $this->error('Không có quyền truy cập doanh nghiệp này.', 403);
        }

        $doanhNghiep->load(['chuSoHuu', 'nguoiDaiDien', 'memberCompanies.member', 'dnTrangThai', 'dnLoaiHinh', 'nganhNgheKdChinh', 'donVi', 'createdByUser']);

        return $this->success(new DoanhNghiepResource($doanhNghiep));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDoanhNghiepRequest $request, $id): JsonResponse
    {
        $validated = $request->validated();
        $danhSachTV = $validated['danhSachThanhVienGopVon'] ?? null;
        unset($validated['danhSachThanhVienGopVon']);
        $doanhNghiep = DoanhNghiepScopeHelper::query($request->user())->find($id);

        if (!$doanhNghiep) {
            return $this->error("Not found!");
        }

        if (!$this->userCanAccessCompany($doanhNghiep)) {
            return $this->error('Không có quyền truy cập doanh nghiệp này.', 403);
        }

        $data = $this->mapCamelToSnake($validated);
        $data = DoanhNghiepStatusHelper::applyStatus($data, $doanhNghiep);
        $data = DoanhNghiepLoaiHinhHelper::applyLoaiHinh($data, $doanhNghiep);
        $data = DoanhNghiepNganhNgheHelper::apply($data);

        if (!empty($data)) {
            DinhDanhHistoryContext::run(['nguon' => 'cap_nhat'], function () use ($doanhNghiep, $data) {
                $doanhNghiep->update($data);
                $doanhNghiep->save();
            });
        }

        if ($danhSachTV !== null) {
            $doanhNghiep->memberCompanies()->delete();
            $this->syncMembersToCompany($doanhNghiep, $danhSachTV);
        }

        $doanhNghiep->load(['chuSoHuu', 'nguoiDaiDien', 'memberCompanies.member', 'dnTrangThai', 'dnLoaiHinh', 'nganhNgheKdChinh', 'donVi', 'createdByUser']);

        return $this->success(
            new DoanhNghiepResource($doanhNghiep->fresh()),
            'Doanh nghiệp updated successfully!!'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DoanhNghiep $doanhNghiep): JsonResponse
    {
        if (!$this->userCanAccessCompany($doanhNghiep)) {
            return $this->error('Không có quyền truy cập doanh nghiệp này.', 403);
        }

        $doanhNghiep->delete();

        return $this->success(null, 'Doanh nghiệp deleted successfully');
    }

    /**
     * Bulk delete companies by id list.
     */
    public function bulkDestroy(): JsonResponse
    {
        $validated = request()->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $deleted = 0;
        $failed = 0;
        $errors = [];

        foreach ($validated['ids'] as $index => $id) {
            $company = DoanhNghiepScopeHelper::query(request()->user())
                ->whereKey($id)
                ->first();

            if (!$company) {
                $failed++;
                $errors[] = [
                    'id' => $id,
                    'message' => 'Doanh nghiệp không tồn tại hoặc không thuộc phạm vi đơn vị của bạn.',
                ];

                continue;
            }

            try {
                $company->delete();
                $deleted++;
            } catch (\Throwable $exception) {
                $failed++;
                $errors[] = [
                    'id' => $id,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return $this->success(
            [
                'deleted' => $deleted,
                'failed' => $failed,
                'errors' => $errors,
            ],
            "Xóa hàng loạt hoàn tất: {$deleted} thành công, {$failed} lỗi.",
            $deleted > 0 ? 200 : 422,
        );
    }

    public function clearByDonViPreview(): JsonResponse
    {
        DonViDataClearService::assertCanClearByDonVi(request()->user());

        $validated = request()->validate([
            'donViId' => ['required', 'integer', 'exists:don_vis,id'],
        ]);

        $preview = DonViDataClearService::previewDoanhNghiep((int) $validated['donViId']);
        $donVi = DonVi::query()->find($preview['donViId']);

        return $this->success([
            'donViId' => $preview['donViId'],
            'donViMa' => $donVi?->ma,
            'donViTen' => $donVi?->ten,
            'scopeDonViCount' => count($preview['donViIds']),
            'count' => $preview['count'],
        ]);
    }

    public function clearByDonVi(): JsonResponse
    {
        DonViDataClearService::assertCanClearByDonVi(request()->user());

        $validated = request()->validate([
            'donViId' => ['required', 'integer', 'exists:don_vis,id'],
        ]);

        $donViId = (int) $validated['donViId'];
        $donVi = DonVi::query()->findOrFail($donViId);
        $result = DonViDataClearService::clearDoanhNghiep($donViId);

        return $this->success(
            [
                'deleted' => $result['deleted'],
                'donViId' => $result['donViId'],
                'donViMa' => $donVi->ma,
                'donViTen' => $donVi->ten,
                'scopeDonViCount' => count($result['donViIds']),
            ],
            "Đã xóa {$result['deleted']} doanh nghiệp thuộc đơn vị {$donVi->ma} — {$donVi->ten}.",
        );
    }

    /**
     * Update identity verification status of a company.
     */
    public function updateDinhDanh(DoanhNghiep $doanhNghiep): JsonResponse
    {
        if (!$this->userCanAccessCompany($doanhNghiep)) {
            return $this->error('Không có quyền truy cập doanh nghiệp này.', 403);
        }

        $validated = request()->validate([
            'daCapNhatDinhDanh' => ['required', 'boolean'],
        ]);

        DinhDanhHistoryContext::run(['nguon' => 'thu_cong'], function () use ($doanhNghiep, $validated) {
            DoanhNghiepStatusHelper::syncDinhDanhStatus($doanhNghiep, $validated['daCapNhatDinhDanh']);
        });
        $doanhNghiep->load(['chuSoHuu', 'nguoiDaiDien', 'memberCompanies.member', 'dnTrangThai', 'dnLoaiHinh', 'nganhNgheKdChinh', 'donVi', 'createdByUser']);

        return $this->success(
            new DoanhNghiepResource($doanhNghiep->fresh()),
            $validated['daCapNhatDinhDanh'] ? self::DINH_DANH_LABEL_UPDATED : self::DINH_DANH_LABEL_PENDING
        );
    }

    /**
     * Bulk update identity verification status by company business codes.
     */
    public function bulkUpdateDinhDanh(): JsonResponse
    {
        $validated = request()->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.maSoDoanhNghiep' => ['required', 'string', 'max:50'],
            'items.*.daCapNhatDinhDanh' => ['required', 'boolean'],
        ]);

        $updated = 0;
        $failed = 0;
        $errors = [];

        DinhDanhHistoryContext::run(['nguon' => 'hang_loat'], function () use ($validated, &$updated, &$failed, &$errors) {
            foreach ($validated['items'] as $index => $item) {
                $msdn = trim((string) $item['maSoDoanhNghiep']);
                $company = DoanhNghiepScopeHelper::query(request()->user())
                    ->where('ma_so_doanh_nghiep', $msdn)
                    ->first();

                if (!$company) {
                    $failed++;
                    $errors[] = [
                        'row' => $index + 1,
                        'message' => "Không tìm thấy doanh nghiệp với MSDN {$msdn}.",
                    ];
                    continue;
                }

                DoanhNghiepStatusHelper::syncDinhDanhStatus($company, (bool) $item['daCapNhatDinhDanh']);
                $updated++;
            }
        });

        return $this->success(
            [
                'imported' => 0,
                'updated' => $updated,
                'failed' => $failed,
                'errors' => $errors,
            ],
            "Cập nhật định danh hàng loạt: {$updated} thành công, {$failed} lỗi."
        );
    }

    /**
     * Identity update history for a company.
     */
    public function dinhDanhLichSu(DoanhNghiep $doanhNghiep): JsonResponse
    {
        if (!$this->userCanAccessCompany($doanhNghiep)) {
            return $this->error('Không có quyền truy cập doanh nghiệp này.', 403);
        }

        $perPage = min(max((int) request('perPage', 20), 1), 100);

        $logs = $doanhNghiep->dinhDanhLichSu()
            ->with('user:id,name,email')
            ->paginate($perPage);

        return $this->paginated(
            DnDinhDanhLichSuResource::collection($logs),
            'Lấy lịch sử cập nhật định danh thành công',
        );
    }

    /**
     * User thường phải thuộc một đơn vị để gán doanh nghiệp khi tạo/import.
     */
    private function ensureUserHasDonViForAssignment(?User $user): ?JsonResponse
    {
        if ($user === null || DoanhNghiepScopeHelper::hasUnrestrictedScope($user) || $user->don_vi_id !== null) {
            return null;
        }

        return $this->error('Tài khoản chưa gắn đơn vị, không thể thêm doanh nghiệp.', 422);
    }

    /**
     * Sync members to company with pivot data from text input.
     */
    private function syncMembersToCompany(DoanhNghiep $doanhNghiep, array $danhSachTV): void
    {
        foreach ($danhSachTV as $item) {
            if (!is_array($item)) {
                continue;
            }

            $fullName = trim($item['fullName'] ?? '');
            if ($fullName === '') {
                continue;
            }

            $memberId = $item['memberId'] ?? null;

            if (!$memberId) {
                $member = Member::firstOrCreate(
                    ['full_name' => $fullName],
                    ['cccd' => null, 'status' => true]
                );
                $memberId = $member->id;
            }

            $doanhNghiep->members()->attach($memberId, [
                'date_join' => $item['dateJoin'] ?? null,
                'position' => $item['position'] ?? null,
                'investment_amount' => $item['investmentAmount'] ?? null,
            ]);
        }
    }

    /**
     * Build filtered query shared by index and export.
     */
    private function buildFilteredQuery(): Builder
    {
        $user = request()->user();
        $requestedDonViId = DoanhNghiepScopeHelper::resolveRequestedDonViFilterId($user);
        $query = DoanhNghiepScopeHelper::query($user)
            ->with(['nguoiDaiDien', 'chuSoHuu', 'memberCompanies.member', 'dnTrangThai', 'dnLoaiHinh', 'nganhNgheKdChinh', 'donVi', 'createdByUser', 'quanHuyenCu', 'xaPhuongCu', 'tinhThanh', 'xaPhuong', 'taxManagement']);

        if ($requestedDonViId !== null) {
            $scopeDonViIds = DoanhNghiepScopeHelper::resolveDonViFilterIds($user, $requestedDonViId);
            if ($scopeDonViIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('don_vi_id', $scopeDonViIds);
            }
        }

        return $query
            ->when(request('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('ten_doanh_nghiep', 'like', "%{$search}%")
                        ->orWhere('ma_so_doanh_nghiep', 'like', "%{$search}%")
                        ->orWhere('dia_chi', 'like', "%{$search}%");
                });
            })
            ->when(request('quanHuyen'), function ($query, $quanHuyen) {
                $query->where('quan_huyen', $quanHuyen);
            })
            ->when(request('phuongXa'), function ($query, $phuongXa) {
                $query->where('phuong_xa', $phuongXa);
            })
            ->when(request('trangThai'), function ($query, $trangThai) {
                $query->where('trang_thai', $trangThai);
            })
            ->when(request('dnTrangThaiId'), function ($query, $dnTrangThaiId) {
                $query->where('dn_trang_thai_id', $dnTrangThaiId);
            })
            ->when(request('loaiHinhDN'), function ($query, $loaiHinhDN) {
                $query->where(function ($builder) use ($loaiHinhDN) {
                    $builder
                        ->where('loai_hinh_dn', $loaiHinhDN)
                        ->orWhereHas('dnLoaiHinh', fn ($q) => $q->where('ten', $loaiHinhDN));
                });
            })
            ->when(request('loaiHinhId'), function ($query, $loaiHinhId) {
                $query->where('dn_loai_hinh_id', $loaiHinhId);
            })
            ->when(request('loaiDN'), function ($query, $loaiDN) {
                $query->where('loai_dn', $loaiDN);
            })
            ->when(request()->has('daCapNhatDinhDanh'), function ($query) {
                $daCapNhatDinhDanh = filter_var(request('daCapNhatDinhDanh'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($daCapNhatDinhDanh !== null) {
                    $query->where('da_cap_nhat_dinh_danh', $daCapNhatDinhDanh);
                }
            })
            ->when(request()->has('hasCoordinates'), function ($query) {
                $hasCoordinates = filter_var(request('hasCoordinates'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($hasCoordinates === true) {
                    $query->whereNotNull('long')->whereNotNull('lat');
                } elseif ($hasCoordinates === false) {
                    $query->where(function ($q) {
                        $q->whereNull('long')->orWhereNull('lat');
                    });
                }
            })
            ->when(request('sortBy'), function ($query, $sortBy) {
                $direction = request('sortDirection', 'asc');
                $allowedSorts = [
                    'tt', 'ma_so_doanh_nghiep', 'ten_doanh_nghiep',
                    'quan_huyen', 'phuong_xa', 'trang_thai',
                    'loai_hinh_dn', 'so_luong_lao_dong', 'created_at',
                ];
                if (in_array($sortBy, $allowedSorts)) {
                    $query->orderBy($sortBy, $direction);
                }
            }, function ($query) {
                $query->orderBy('created_at', 'desc');
            });
    }

    /**
     * Map camelCase keys to snake_case for database storage.
     */
    private function mapCamelToSnake(array $data): array
    {
        $mapping = [
            'maSoDoanhNghiep' => 'ma_so_doanh_nghiep',
            'tenDoanhNghiep' => 'ten_doanh_nghiep',
            'diaChi' => 'dia_chi',
            'long' => 'long',
            'lat' => 'lat',
            'quanHuyen' => 'quan_huyen',
            'phuongXa' => 'phuong_xa',
            'vonDieuLe' => 'von_dieu_le',
            'trangThai' => 'trang_thai',
            'dnTrangThaiId' => 'dn_trang_thai_id',
            'lyDoTrangThai' => 'ly_do_trang_thai',
            'daCapNhatDinhDanh' => 'da_cap_nhat_dinh_danh',
            'dienThoai' => 'dien_thoai',
            'nguoiDaiDienTen' => 'nguoi_dai_dien_ten',
            'ngaySinhNguoiDaiDien' => 'ngay_sinh_nguoi_dai_dien',
            'chuSoHuuTen' => 'chu_so_huu_ten',
            'nguoiDaiDienID' => 'nguoi_dai_dien_id',
            'chuSoHuuID' => 'chu_so_huu_id',
            'nganhNgheKDChinh' => 'nganh_nghe_kd_chinh',
            'nganhNgheKD' => 'nganh_nghe_kd',
            'ngayCap' => 'ngay_cap',
            'ngayDangKyThayDoi' => 'ngay_dang_ky_thay_doi',
            'loaiHinhDN' => 'loai_hinh_dn',
            'dnLoaiHinhId' => 'dn_loai_hinh_id',
            'soLuongLaoDong' => 'so_luong_lao_dong',
            'loaiDN' => 'loai_dn',
            'dsCoDong' => 'ds_co_dong',
        ];

        $result = [];
        foreach ($data as $key => $value) {
            $result[$mapping[$key] ?? $key] = $value;
        }

        return $result;
    }

    private function userCanAccessCompany(DoanhNghiep $doanhNghiep): bool
    {
        return DoanhNghiepScopeHelper::userCanAccess(request()->user(), $doanhNghiep);
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

    /**
     * @return array<string, string|array<string, mixed>>|null
     */
    private function parseImportValueExtensions(mixed $input): ?array
    {
        if ($input === null || $input === '') {
            return null;
        }

        if (is_string($input)) {
            $decoded = json_decode($input, true);

            return is_array($decoded) && $decoded !== [] ? $decoded : null;
        }

        return is_array($input) && $input !== [] ? $input : null;
    }
}
