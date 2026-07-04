<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreUserRequest;
use App\Http\Requests\Api\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
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
            $query->where('don_vi_id', (int) $request->query('donViId'));
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

    public function store(StoreUserRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $user = User::query()->create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => $payload['password'],
            'role_id' => $payload['roleId'] ?? null,
            'don_vi_id' => $payload['donViId'] ?? null,
            'is_active' => $payload['isActive'] ?? true,
        ]);

        $user->load(['role', 'donVi']);

        return $this->success(new UserResource($user), 'Tạo người dùng thành công', 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['role.permissions', 'donVi']);

        return $this->success(new UserResource($user));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
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
            $data['role_id'] = $payload['roleId'];
        }
        if (array_key_exists('donViId', $payload)) {
            $data['don_vi_id'] = $payload['donViId'];
        }
        if (array_key_exists('isActive', $payload)) {
            $data['is_active'] = (bool) $payload['isActive'];
        }

        $user->update($data);
        $user->load(['role', 'donVi']);

        return $this->success(new UserResource($user->fresh(['role', 'donVi'])), 'Cập nhật người dùng thành công');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ((int) $request->user()?->id === (int) $user->id) {
            return $this->error('Không thể xóa tài khoản đang đăng nhập.', 422);
        }

        $user->delete();

        return $this->success(null, 'Xóa người dùng thành công');
    }
}
