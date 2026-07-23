<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Support\AuthProfileCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends ApiController
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! $token = auth('api')->attempt($credentials)) {
            return $this->error('Email hoặc mật khẩu không đúng', 401);
        }

        /** @var User $user */
        $user = auth('api')->user();

        if (! $user->is_active) {
            auth('api')->logout();

            return $this->error('Tài khoản đã bị vô hiệu hóa', 403);
        }

        return $this->success([
            'token' => $token,
            'tokenType' => 'bearer',
            'expiresIn' => auth('api')->factory()->getTTL() * 60,
            'user' => $this->profilePayload($user),
        ], 'Đăng nhập thành công');
    }

    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();

        return $this->success($this->profilePayload($user));
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return $this->success(null, 'Đăng xuất thành công');
    }

    public function refresh(): JsonResponse
    {
        $token = auth('api')->refresh();

        /** @var User $user */
        $user = auth('api')->user();

        return $this->success([
            'token' => $token,
            'tokenType' => 'bearer',
            'expiresIn' => auth('api')->factory()->getTTL() * 60,
            'user' => $this->profilePayload($user),
        ], 'Làm mới token thành công');
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(User $user): array
    {
        $fingerprint = implode(':', [
            (string) ($user->updated_at?->timestamp ?? 0),
            (string) ($user->role_id ?? 0),
            (string) ($user->don_vi_id ?? 0),
            AuthProfileCache::userBustToken((int) $user->id),
        ]);

        return AuthProfileCache::rememberMe((int) $user->id, $fingerprint, function () use ($user) {
            $fresh = User::query()
                ->select(['id', 'name', 'email', 'is_active', 'role_id', 'don_vi_id', 'created_at', 'updated_at'])
                ->with([
                    'role:id,name,slug,level,description',
                    'donVi:id,parent_id,cap,ma,ten,is_active',
                ])
                ->findOrFail($user->id);

            return (new AuthUserResource($fresh))->resolve();
        });
    }
}
