<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreUserRequest;
use App\Http\Requests\Api\UpdateUserRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Support\AuthProfileCache;
use App\Support\RoleHierarchyHelper;
use App\Support\UserScopeHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = UserScopeHelper::query($request->user())
            ->with(['role', 'donVi'])
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->query('search')) . '%';
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        if ($request->filled('roleId')) {
            $query->where('role_id', (int) $request->query('roleId'));
        }

        if ($request->filled('donViId')) {
            $donViId = (int) $request->query('donViId');
            $allowedIds = UserScopeHelper::allowedDonViIds($request->user());
            if ($allowedIds !== null && !in_array($donViId, $allowedIds, true)) {
                return $this->error('Không có quyền lọc theo đơn vị này.', 403);
            }
            $query->where('don_vi_id', $donViId);
        }

        if ($request->has('isActive')) {
            $isActive = filter_var($request->query('isActive'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        $perPage = min(max((int) $request->query('perPage', 20), 1), 100);

        return $this->paginated(
            UserResource::collection($query->paginate($perPage)),
            'Lấy danh sách người dùng thành công',
        );
    }

    public function assignableRoles(Request $request): JsonResponse
    {
        $roles = RoleHierarchyHelper::assignableRolesQuery($request->user())
            ->get();

        return $this->success(
            RoleResource::collection($roles),
            'Lấy danh sách vai trò có thể gán thành công',
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $actor = $request->user();
        $payload = $request->validated();

        $role = isset($payload['roleId']) ? Role::query()->find($payload['roleId']) : null;
        if ($role && !RoleHierarchyHelper::canAssignRole($actor, $role)) {
            return $this->error('Không có quyền gán vai trò này.', 403);
        }

        $donViId = UserScopeHelper::resolveDonViIdForCreate($actor, $payload['donViId'] ?? null);
        if (!UserScopeHelper::donViIdIsAllowed($actor, $donViId)) {
            return $this->error('Không có quyền gán đơn vị này.', 403);
        }

        if (!RoleHierarchyHelper::isRootUser($actor) && $donViId === null) {
            return $this->error('Tài khoản quản trị chưa gắn đơn vị, không thể tạo người dùng.', 422);
        }

        $user = User::query()->create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => $payload['password'],
            'role_id' => $role?->id,
            'don_vi_id' => $donViId,
            'is_active' => $payload['isActive'] ?? true,
        ]);

        $user->load(['role', 'donVi']);

        return $this->success(new UserResource($user), 'Tạo người dùng thành công', 201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        if (!RoleHierarchyHelper::canManageUser($request->user(), $user)) {
            return $this->error('Không có quyền xem người dùng này.', 403);
        }

        $user->load(['role.permissions', 'donVi']);

        return $this->success(new UserResource($user));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if (!RoleHierarchyHelper::canManageUser($actor, $user)) {
            return $this->error('Không có quyền cập nhật người dùng này.', 403);
        }

        $payload = $request->validated();
        $data = [];

        if (array_key_exists('name', $payload)) {
            $data['name'] = $payload['name'];
        }
        if (array_key_exists('email', $payload)) {
            $data['email'] = $payload['email'];
        }
        if (!empty($payload['password'])) {
            $data['password'] = $payload['password'];
        }
        if (array_key_exists('roleId', $payload)) {
            $role = $payload['roleId'] ? Role::query()->find($payload['roleId']) : null;
            if ($role && !RoleHierarchyHelper::canAssignRole($actor, $role)) {
                return $this->error('Không có quyền gán vai trò này.', 403);
            }
            $data['role_id'] = $role?->id;
        }
        if (array_key_exists('donViId', $payload)) {
            if (!UserScopeHelper::canChangeDonVi($actor)) {
                // Quản trị đơn vị không đổi đơn vị — giữ nguyên đơn vị người tạo/quản lý
            } else {
                $donViId = $payload['donViId'];
                if (!UserScopeHelper::donViIdIsAllowed($actor, $donViId)) {
                    return $this->error('Không có quyền gán đơn vị này.', 403);
                }
                $data['don_vi_id'] = $donViId;
            }
        }
        if (array_key_exists('isActive', $payload)) {
            $data['is_active'] = (bool) $payload['isActive'];
        }

        $user->update($data);
        AuthProfileCache::forgetUser((int) $user->id);
        $user->load(['role', 'donVi']);

        return $this->success(new UserResource($user->fresh(['role', 'donVi'])), 'Cập nhật người dùng thành công');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ((int) $request->user()?->id === (int) $user->id) {
            return $this->error('Không thể xóa tài khoản đang đăng nhập.', 422);
        }

        if (!RoleHierarchyHelper::canManageUser($request->user(), $user)) {
            return $this->error('Không có quyền xóa người dùng này.', 403);
        }

        $user->delete();

        return $this->success(null, 'Xóa người dùng thành công');
    }
}
