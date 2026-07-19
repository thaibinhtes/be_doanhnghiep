<?php

namespace App\Http\Controllers\Api;

use App\Exports\HopTacXaExport;
use App\Exports\HopTacXaTemplateExport;
use App\Http\Resources\HopTacXaResource;
use App\Jobs\ProcessHopTacXaImportJob;
use App\Models\DonVi;
use App\Models\HopTacXa;
use App\Models\HopTacXaImportJob;
use App\Models\User;
use App\Support\DoanhNghiepScopeHelper;
use App\Support\DonViDataClearService;
use App\Support\HopTacXaExcelColumns;
use App\Support\HopTacXaImportColumnMap;
use App\Support\HopTacXaScopeHelper;
use App\Support\ImportExcelKindDetector;
use App\Support\ImportExcelKindGuard;
use App\Support\ImportJobScopeHelper;
use App\Support\ImportSocketNotifier;
use App\Support\ImportSocketTopics;
use App\Support\ImportUploadLogger;
use App\Support\ImportUploadValidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HopTacXaController extends ApiController
{
    public function index(): AnonymousResourceCollection
    {
        $perPage = min(max((int) request('per_page', request('perPage', 50)), 1), 500);
        $items = $this->buildFilteredQuery()->paginate($perPage);

        return HopTacXaResource::collection($items);
    }

    public function export(): BinaryFileResponse
    {
        $filename = 'hop-tac-xa_' . now()->format('Y-m-d_His') . '.xlsx';

        $query = $this->buildFilteredQuery();
        if (!request('sortBy')) {
            $query->reorder()->orderByRaw('tt IS NULL')->orderBy('tt')->orderBy('id');
        }

        return Excel::download(new HopTacXaExport($query), $filename);
    }

    public function exportTemplate(): BinaryFileResponse
    {
        return Excel::download(
            new HopTacXaTemplateExport(),
            'mau-import-hop-tac-xa.xlsx'
        );
    }

    public function importColumnMap(): JsonResponse
    {
        $defaults = HopTacXaImportColumnMap::defaultStcExampleFormat();

        return $this->success([
            'startRow' => $defaults['start_row'],
            'columnMap' => HopTacXaImportColumnMap::STC_FORMAT_COLUMN_MAP,
            'columnLabels' => HopTacXaExcelColumns::columnLabels(),
            'valueExtensions' => [],
            'defaultConfigCode' => HopTacXaImportColumnMap::STC_EXAMPLE_CONFIG_CODE,
        ]);
    }

    public function import(): JsonResponse
    {
        ImportUploadValidator::validate(request(), 'hop_tac_xa_import', [
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
            : HopTacXaImportColumnMap::DEFAULT_START_ROW;
        $columnMap = $this->parseImportColumnMap(request()->input('columnMap'));
        ImportExcelKindGuard::assertHopTacXaColumnMap($columnMap);
        $useColumnMap = request()->has('startRow') || $columnMap !== null;

        try {
            $uploadedFile = request()->file('file');
            $storedPath = $uploadedFile->store('imports/pending');
        } catch (\Throwable $e) {
            ImportUploadLogger::exception('hop_tac_xa_import', request(), $e, 'store_file');
            ImportUploadValidator::throwError(
                'Không lưu được file upload. Kiểm tra quyền thư mục storage/app/imports.',
                'store_failed',
            );
        }

        ImportExcelKindGuard::assertExpectedKind(
            Storage::disk('local')->path($storedPath),
            ImportExcelKindDetector::KIND_HTX,
        );

        $importJob = HopTacXaImportJob::query()->create([
            'user_id' => $user->id,
            'don_vi_id' => ImportJobScopeHelper::resolveDonViId($user),
            'status' => HopTacXaImportJob::STATUS_PENDING,
            'type' => HopTacXaImportJob::TYPE_COOPERATIVES,
            'file_path' => $storedPath,
            'original_filename' => $uploadedFile->getClientOriginalName(),
            'start_row' => $startRow,
            'column_map' => $columnMap ?? HopTacXaImportColumnMap::normalizeStoredColumnMap(HopTacXaImportColumnMap::STC_FORMAT_COLUMN_MAP),
            'value_extensions' => null,
            'use_column_map' => $useColumnMap,
        ]);

        ProcessHopTacXaImportJob::dispatch($importJob->id);

        ImportUploadLogger::succeeded('hop_tac_xa_import', request(), [
            'import_job_id' => $importJob->id,
            'stored_path' => $storedPath,
            'original_filename' => $importJob->original_filename,
        ]);

        ImportSocketNotifier::notify(
            $user->id,
            ImportSocketTopics::EXCEL_STARTED,
            $importJob->id,
            [
                'status' => HopTacXaImportJob::STATUS_PENDING,
                'originalFilename' => $importJob->original_filename,
                'entity' => 'hop-tac-xa',
            ],
        );

        return $this->success(
            [
                'importJobId' => $importJob->id,
                'status' => $importJob->status,
                'originalFilename' => $importJob->original_filename,
            ],
            'Đã đưa file import vào hàng đợi. Bạn sẽ nhận thông báo khi hoàn tất.',
            202,
        );
    }

    public function store(): JsonResponse
    {
        $data = $this->validatePayload();
        $user = request()->user();

        if ($response = $this->ensureUserHasDonViForAssignment($user)) {
            return $response;
        }

        if ($user) {
            $data['don_vi_id'] = HopTacXaScopeHelper::resolveAssignmentDonViId($user);
            $data['created_by_user_id'] = $user->id;
        }

        $hopTacXa = HopTacXa::create($data);
        $hopTacXa->load(['donVi', 'createdByUser']);

        return $this->success(new HopTacXaResource($hopTacXa), 'Tạo hợp tác xã thành công', 201);
    }

    public function show(HopTacXa $hopTacXa): JsonResponse
    {
        if (!$this->userCanAccess($hopTacXa)) {
            return $this->error('Không có quyền truy cập hợp tác xã này.', 403);
        }

        $hopTacXa->load(['donVi', 'createdByUser']);

        return $this->success(new HopTacXaResource($hopTacXa));
    }

    public function update(HopTacXa $hopTacXa): JsonResponse
    {
        if (!$this->userCanAccess($hopTacXa)) {
            return $this->error('Không có quyền cập nhật hợp tác xã này.', 403);
        }

        $data = $this->validatePayload(isUpdate: true);
        $hopTacXa->update($data);
        $hopTacXa->load(['donVi', 'createdByUser']);

        return $this->success(new HopTacXaResource($hopTacXa), 'Cập nhật hợp tác xã thành công');
    }

    public function destroy(HopTacXa $hopTacXa): JsonResponse
    {
        if (!$this->userCanAccess($hopTacXa)) {
            return $this->error('Không có quyền xóa hợp tác xã này.', 403);
        }

        $hopTacXa->delete();

        return $this->success(null, 'Xóa hợp tác xã thành công');
    }

    public function bulkDestroy(): JsonResponse
    {
        $ids = request()->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'];

        $accessibleIds = HopTacXaScopeHelper::query(request()->user())
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $deleted = 0;
        $errors = [];

        foreach ($ids as $id) {
            if (!in_array((int) $id, $accessibleIds, true)) {
                $errors[] = ['id' => (int) $id, 'message' => 'Không có quyền hoặc không tồn tại.'];
                continue;
            }
        }

        if ($accessibleIds !== []) {
            $deleted = HopTacXa::query()->whereIn('id', $accessibleIds)->delete();
        }

        return $this->success([
            'deleted' => $deleted,
            'errors' => $errors,
        ], 'Xóa hàng loạt hoàn tất');
    }

    public function clearByDonViPreview(): JsonResponse
    {
        DonViDataClearService::assertCanClearByDonVi(request()->user());

        $validated = request()->validate([
            'donViId' => ['required', 'integer', 'exists:don_vis,id'],
        ]);

        $preview = DonViDataClearService::previewHopTacXa((int) $validated['donViId']);
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
        $result = DonViDataClearService::clearHopTacXa($donViId);

        return $this->success(
            [
                'deleted' => $result['deleted'],
                'donViId' => $result['donViId'],
                'donViMa' => $donVi->ma,
                'donViTen' => $donVi->ten,
                'scopeDonViCount' => count($result['donViIds']),
            ],
            "Đã xóa {$result['deleted']} hợp tác xã thuộc đơn vị {$donVi->ma} — {$donVi->ten}.",
        );
    }

    private function buildFilteredQuery(): Builder
    {
        $user = request()->user();
        $requestedDonViId = DoanhNghiepScopeHelper::resolveRequestedDonViFilterId($user);
        $query = HopTacXaScopeHelper::query($user)
            ->with(['donVi', 'createdByUser', 'taxManagement']);

        if ($requestedDonViId !== null) {
            $scopeDonViIds = HopTacXaScopeHelper::resolveDonViFilterIds($user, $requestedDonViId);
            if ($scopeDonViIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('don_vi_id', $scopeDonViIds);
            }
        }

        return $query
            ->when(request('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('ten_htx', 'like', "%{$search}%")
                        ->orWhere('ma_so_thue', 'like', "%{$search}%")
                        ->orWhere('dia_chi', 'like', "%{$search}%")
                        ->orWhere('chu_tich_hdqt_ten', 'like', "%{$search}%");
                });
            })
            ->when(request('phuongXa'), fn ($query, $phuongXa) => $query->where('phuong_xa', $phuongXa))
            ->when(request('linhVuc'), fn ($query, $linhVuc) => $query->where('linh_vuc', $linhVuc))
            ->when(request('hoatDong'), fn ($query, $hoatDong) => $query->where('hoat_dong', $hoatDong))
            ->orderByRaw('tt IS NULL')
            ->orderBy('tt')
            ->orderBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(bool $isUpdate = false): array
    {
        $rules = [
            'tt' => ['nullable', 'integer'],
            'tenHtx' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'maSoThue' => ['nullable', 'string', 'max:50'],
            'namThanhLap' => ['nullable', 'string', 'max:10'],
            'chuTichHdqtTen' => ['nullable', 'string', 'max:255'],
            'dienThoai' => ['nullable', 'string', 'max:50'],
            'diaChi' => ['nullable', 'string'],
            'phuongXa' => ['nullable', 'string', 'max:150'],
            'diaChiCu' => ['nullable', 'string'],
            'diaChiMoi' => ['nullable', 'string'],
            'phuongXaCu' => ['nullable', 'string', 'max:255'],
            'phuongXaMoi' => ['nullable', 'string', 'max:255'],
            'quanHuyenCu' => ['nullable', 'string', 'max:255'],
            'quanHuyenMoi' => ['nullable', 'string', 'max:255'],
            'tinhThanhCu' => ['nullable', 'string', 'max:255'],
            'dienTichHa' => ['nullable', 'numeric', 'min:0'],
            'vonDieuLe' => ['nullable', 'string', 'max:100'],
            'soThanhVien' => ['nullable', 'integer', 'min:0'],
            'soNguoiLaoDong' => ['nullable', 'integer', 'min:0'],
            'linhVuc' => ['nullable', 'string', 'max:255'],
            'hoatDong' => ['nullable', 'string', 'max:255'],
            'dsThanhVien' => ['nullable', 'string'],
            'ghiChu' => ['nullable', 'string'],
        ];

        $validated = request()->validate($rules);

        return HopTacXaExcelColumns::mapToSnake($validated);
    }

    private function ensureUserHasDonViForAssignment(?User $user): ?JsonResponse
    {
        if ($user === null || DoanhNghiepScopeHelper::hasUnrestrictedScope($user) || $user->don_vi_id !== null) {
            return null;
        }

        return $this->error('Tài khoản chưa gắn đơn vị, không thể thao tác hợp tác xã.', 422);
    }

    private function userCanAccess(HopTacXa $hopTacXa): bool
    {
        return HopTacXaScopeHelper::userCanAccess(request()->user(), $hopTacXa);
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
