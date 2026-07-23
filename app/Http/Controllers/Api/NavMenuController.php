<?php

namespace App\Http\Controllers\Api;

use App\Support\NavMenuService;
use App\Support\RoleHierarchyHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NavMenuController extends ApiController
{
    public function __construct(
        private readonly NavMenuService $navMenuService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $user->loadMissing('role');

        return $this->success(
            $this->navMenuService->treeForUser($user),
            'Lấy menu thành công',
        );
    }

    public function admin(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        if (!RoleHierarchyHelper::isRootUser($user)) {
            return $this->error('Chỉ tài khoản ROOT mới được cấu hình menu.', 403);
        }

        return $this->success(
            $this->navMenuService->adminTree(),
            'Lấy cấu hình menu thành công',
        );
    }

    public function reorder(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        if (!RoleHierarchyHelper::isRootUser($user)) {
            return $this->error('Chỉ tài khoản ROOT mới được cấu hình menu.', 403);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:nav_menu_items,id'],
            'items.*.parentId' => ['nullable', 'integer', 'exists:nav_menu_items,id'],
            'items.*.sortOrder' => ['required', 'integer', 'min:0', 'max:9999'],
            'items.*.label' => ['nullable', 'string', 'max:255'],
        ]);

        $this->navMenuService->reorder($user, $validated['items']);

        return $this->success(
            $this->navMenuService->adminTree(),
            'Cập nhật cấu hình menu thành công',
        );
    }

    public function sync(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        if (!RoleHierarchyHelper::isRootUser($user)) {
            return $this->error('Chỉ tài khoản ROOT mới được đồng bộ menu.', 403);
        }

        $this->navMenuService->syncFromRegistry();

        return $this->success(
            [
                'total' => $this->navMenuService->count(),
                'tree' => $this->navMenuService->adminTree(),
            ],
            'Đồng bộ menu mặc định thành công (giữ tên và thứ tự đã cấu hình)',
        );
    }
}
