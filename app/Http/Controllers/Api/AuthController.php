<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\UserResource;
use App\Models\User;
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

        if (!$token = auth('api')->attempt($credentials)) {
            return $this->error('Email hoặc mật khẩu không đúng', 401);
        }

        /** @var User $user */
        $user = auth('api')->user();

        if (!$user->is_active) {
            auth('api')->logout();

            return $this->error('Tài khoản đã bị vô hiệu hóa', 403);
        }

        $user->load(['role.permissions']);

        return $this->success([
            'token' => $token,
            'tokenType' => 'bearer',
            'expiresIn' => auth('api')->factory()->getTTL() * 60,
            'user' => new UserResource($user),
        ], 'Đăng nhập thành công');
    }

    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();
        $user->load(['role.permissions']);

        return $this->success(new UserResource($user));
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
        $user->load(['role.permissions']);

        return $this->success([
            'token' => $token,
            'tokenType' => 'bearer',
            'expiresIn' => auth('api')->factory()->getTTL() * 60,
            'user' => new UserResource($user),
        ], 'Làm mới token thành công');
    }
}
