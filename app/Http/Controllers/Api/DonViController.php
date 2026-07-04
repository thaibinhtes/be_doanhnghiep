<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreDonViRequest;
use App\Http\Requests\Api\UpdateDonViRequest;
use App\Http\Resources\DonViResource;
use App\Models\DonVi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonViController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('tree')) {
            $roots = DonVi::query()
                ->with(['children' => fn ($q) => $this->applyTreeChildrenConstraints($q, $request)])
                ->withCount(['children', 'users', 'doanhNghieps'])
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

            return $this->success(DonViResource::collection($roots));
        }

        $query = DonVi::query()
            ->with('parent:id,cap,ma,ten')
            ->withCount(['children', 'users', 'doanhNghieps'])
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

        if ($request->boolean('all')) {
            return $this->success(
                DonViResource::collection($query->get()),
                'Lấy danh sách đơn vị thành công',
            );
        }

        $perPage = min(max((int) $request->query('perPage', 50), 1), 200);

        return $this->paginated(
            DonViResource::collection($query->paginate($perPage)),
            'Lấy danh sách đơn vị thành công',
        );
    }

    public function store(StoreDonViRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $item = DonVi::query()->create([
            'parent_id' => $payload['parentId'] ?? null,
            'cap' => $payload['cap'],
            'ma' => $payload['ma'],
            'ten' => $payload['ten'],
            'mo_ta' => $payload['moTa'] ?? null,
            'thu_tu' => $payload['thuTu'] ?? 0,
            'is_active' => $payload['isActive'] ?? true,
        ]);

        $item->load('parent')->loadCount(['children', 'users', 'doanhNghieps']);

        return $this->success(new DonViResource($item), 'Tạo đơn vị thành công', 201);
    }

    public function show(DonVi $donVi): JsonResponse
    {
        $donVi->load(['parent', 'children' => fn ($q) => $q->orderBy('thu_tu')->orderBy('ma')])
            ->loadCount(['children', 'users', 'doanhNghieps']);

        return $this->success(new DonViResource($donVi));
    }

    public function update(UpdateDonViRequest $request, DonVi $donVi): JsonResponse
    {
        $payload = $request->validated();
        $data = [];

        if (array_key_exists('ten', $payload)) {
            $data['ten'] = $payload['ten'];
        }
        if (array_key_exists('moTa', $payload)) {
            $data['mo_ta'] = $payload['moTa'];
        }
        if (array_key_exists('thuTu', $payload)) {
            $data['thu_tu'] = $payload['thuTu'];
        }
        if (array_key_exists('isActive', $payload)) {
            $data['is_active'] = (bool) $payload['isActive'];
        }

        $donVi->update($data);

        if (array_key_exists('is_active', $data) && $data['is_active'] === false) {
            $this->deactivateDescendants($donVi);
        }

        $donVi->load('parent')->loadCount(['children', 'users', 'doanhNghieps']);

        return $this->success(new DonViResource($donVi->fresh(['parent'])), 'Cập nhật đơn vị thành công');
    }

    public function destroy(DonVi $donVi): JsonResponse
    {
        if ($donVi->children()->exists()) {
            return $this->error('Không thể xóa đơn vị đang có đơn vị con.', 422);
        }

        if ($donVi->users()->exists()) {
            return $this->error('Không thể xóa đơn vị đang có người dùng.', 422);
        }

        if ($donVi->doanhNghieps()->exists()) {
            return $this->error('Không thể xóa đơn vị đang có doanh nghiệp.', 422);
        }

        $donVi->delete();

        return $this->success(null, 'Xóa đơn vị thành công');
    }

    private function deactivateDescendants(DonVi $item): void
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
            ->withCount(['children', 'users', 'doanhNghieps'])
            ->when($request->has('isActive'), function ($q) use ($request) {
                $isActive = filter_var($request->query('isActive'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($isActive !== null) {
                    $q->where('is_active', $isActive);
                }
            })
            ->orderBy('thu_tu')
            ->orderBy('ma');
    }
}
