<?php

namespace App\Http\Controllers\Api;

use App\Exports\DanhMucNganhNgheExport;
use App\Http\Requests\Api\StoreDanhMucNganhNgheRequest;
use App\Http\Requests\Api\UpdateDanhMucNganhNgheRequest;
use App\Http\Resources\DanhMucNganhNgheResource;
use App\Imports\DanhMucNganhNgheImport;
use App\Models\DanhMucNganhNghe;
use App\Models\DonVi;
use App\Support\DanhMucNganhNgheSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DanhMucNganhNgheController extends ApiController
{
    public function __construct(
        private readonly DanhMucNganhNgheSyncService $syncService,
    ) {}

    public function exportCatalog(): BinaryFileResponse
    {
        $filename = 'danh-muc-nganh-nghe_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new DanhMucNganhNgheExport(), $filename);
    }

    public function importCatalog(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $import = new DanhMucNganhNgheImport($this->syncService);
        Excel::import($import, $request->file('file'));
        $result = $import->getResult();

        return $this->success(
            $result,
            "Đồng bộ danh mục ngành: {$result['imported']} mới, {$result['skipped']} bỏ qua, {$result['failed']} lỗi.",
        );
    }
    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('tree')) {
            $roots = DanhMucNganhNghe::query()
                ->with(['children' => fn ($q) => $this->applyTreeChildrenConstraints($q, $request)])
                ->withCount('children')
                ->whereNull('parent_id')
                ->when($request->filled('isActive'), function ($q) use ($request) {
                    $isActive = filter_var($request->query('isActive'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($isActive !== null) {
                        $q->where('is_active', $isActive);
                    }
                })
                ->orderBy('thu_tu')
                ->orderBy('ma')
                ->get();

            return $this->success(DanhMucNganhNgheResource::collection($roots));
        }

        $query = DanhMucNganhNghe::query()
            ->with('parent:id,cap,ma,ten')
            ->withCount('children')
            ->orderBy('cap')
            ->orderBy('thu_tu')
            ->orderBy('ma');

        if ($request->has('parentId')) {
            $parentId = $request->query('parentId');
            if ($parentId === '' || $parentId === 'null') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $parentId);
            }
        }

        if ($request->filled('cap')) {
            $query->where('cap', (int) $request->query('cap'));
        }

        if ($request->has('isActive')) {
            $isActive = filter_var($request->query('isActive'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->query('search')) . '%';
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('ma', 'like', $search)
                    ->orWhere('ten', 'like', $search);
            });
        }

        if ($request->filled('mas')) {
            $codes = array_values(array_filter(array_map(
                static fn ($code) => trim((string) $code),
                explode(',', (string) $request->query('mas'))
            )));

            if ($codes !== []) {
                $query->whereIn('ma', $codes);
            }
        }

        if ($request->boolean('all')) {
            return $this->success(
                DanhMucNganhNgheResource::collection($query->get()),
                'Lấy danh mục ngành nghề thành công',
            );
        }

        $perPage = min(max((int) $request->query('perPage', 50), 1), 200);

        return $this->paginated(
            DanhMucNganhNgheResource::collection($query->paginate($perPage)),
            'Lấy danh mục ngành nghề thành công',
        );
    }

    public function store(StoreDanhMucNganhNgheRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $item = DanhMucNganhNghe::query()->create([
            'parent_id' => $payload['parentId'] ?? null,
            'cap' => $payload['cap'],
            'ma' => $payload['ma'],
            'ten' => $payload['ten'],
            'thu_tu' => $payload['thuTu'] ?? 0,
            'is_active' => $payload['isActive'] ?? true,
        ]);

        $item->load('parent')->loadCount('children');

        return $this->success(new DanhMucNganhNgheResource($item), 'Tạo danh mục ngành thành công', 201);
    }

    public function show(DanhMucNganhNghe $danhMucNganhNghe): JsonResponse
    {
        $danhMucNganhNghe->load(['parent', 'children' => fn ($q) => $q->orderBy('thu_tu')->orderBy('ma')])
            ->loadCount('children');

        return $this->success(new DanhMucNganhNgheResource($danhMucNganhNghe));
    }

    public function update(UpdateDanhMucNganhNgheRequest $request, DanhMucNganhNghe $danhMucNganhNghe): JsonResponse
    {
        $payload = $request->validated();
        $data = [];

        if (array_key_exists('ten', $payload)) {
            $data['ten'] = $payload['ten'];
        }
        if (array_key_exists('thuTu', $payload)) {
            $data['thu_tu'] = $payload['thuTu'];
        }
        if (array_key_exists('isActive', $payload)) {
            $data['is_active'] = (bool) $payload['isActive'];
        }

        $danhMucNganhNghe->update($data);

        if (array_key_exists('is_active', $data) && $data['is_active'] === false) {
            $this->deactivateDescendants($danhMucNganhNghe);
        }

        $danhMucNganhNghe->load('parent')->loadCount('children');

        return $this->success(new DanhMucNganhNgheResource($danhMucNganhNghe->fresh(['parent'])), 'Cập nhật danh mục ngành thành công');
    }

    public function destroy(DanhMucNganhNghe $danhMucNganhNghe): JsonResponse
    {
        if ($danhMucNganhNghe->children()->exists()) {
            return $this->error('Không thể xóa danh mục đang có mục con.', 422);
        }

        $danhMucNganhNghe->delete();

        return $this->success(null, 'Xóa danh mục ngành thành công');
    }

    private function deactivateDescendants(DanhMucNganhNghe $item): void
    {
        foreach ($item->children as $child) {
            if ($child->is_active) {
                $child->update(['is_active' => false]);
            }
            $this->deactivateDescendants($child);
        }
    }

    private function applyTreeChildrenConstraints($query, Request $request)
    {
        return $query
            ->with(['children' => fn ($q) => $this->applyTreeChildrenConstraints($q, $request)])
            ->withCount('children')
            ->when($request->has('isActive'), function ($q) use ($request) {
                $isActive = filter_var($request->query('isActive'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($isActive !== null) {
                    $q->where('is_active', $isActive);
                }
            })
            ->orderBy('thu_tu')
            ->orderBy('ma');
    }

    private function ensureRootAdmin(): ?JsonResponse
    {
        $user = request()->user();

        if (!DonVi::userBelongsToRoot($user)) {
            return $this->error('Chỉ quản trị viên thuộc đơn vị ROOT mới được thực hiện thao tác này.', 403);
        }

        return null;
    }
}
