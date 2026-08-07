<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Support\AuthProfileCache;
use App\Support\RecaptchaVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends ApiController
{
    public function __construct(
        private readonly RecaptchaVerifier $recaptcha,
    ) {}

    public function captchaConfig(): JsonResponse
    {
        $enabled = $this->recaptcha->isEnabled();

        return $this->success([
            'enabled' => $enabled,
            'siteKey' => $enabled ? $this->recaptcha->siteKey() : null,
            'action' => $enabled ? $this->recaptcha->action() : null,
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'captchaToken' => [$this->recaptcha->isEnabled() ? 'required' : 'nullable', 'string'],
        ]);

        if ($this->recaptcha->isEnabled()) {
            if (! $this->recaptcha->canVerify()) {
                return $this->error(
                    'Captcha chưa cấu hình đủ (thiếu CAPCHA_API_KEY hoặc CAPCHA_PROJECT_ID).',
                    422,
                );
            }

            $result = $this->recaptcha->verifyDetailed(
                $credentials['captchaToken'] ?? null,
                $request->ip(),
            );

            if (! $result['ok']) {
                $message = match ($result['reason']) {
                    'DUPE' => 'Captcha đã được sử dụng. Vui lòng tích lại captcha rồi đăng nhập.',
                    'EXPIRED', 'TIMEOUT' => 'Captcha đã hết hạn. Vui lòng tích lại captcha.',
                    'BROWSER_ERROR', 'INVALID_REASON_UNSPECIFIED', 'UNKNOWN' => 'Xác minh captcha không thành công. Vui lòng tích lại captcha.',
                    default => 'Xác minh captcha không thành công. Vui lòng thử lại.',
                };

                return $this->error($message, 422);
            }
        }

        unset($credentials['captchaToken']);

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
                ->select(['id', 'name', 'email', 'is_active', 'role_id', 'don_vi_id', 'phong_ban_id', 'chuc_danh', 'created_at', 'updated_at'])
                ->with([
                    'role:id,name,slug,level,description',
                    'donVi:id,parent_id,cap,ma,ten,is_active',
                    'phongBan:id,ma,ten',
                ])
                ->findOrFail($user->id);

            return (new AuthUserResource($fresh))->resolve();
        });
    }
}
