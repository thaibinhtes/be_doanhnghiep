<?php

namespace App\Support;

use App\Models\NavMenuItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NavMenuService
{
    private const USER_TREE_TTL = 3600;

    private const BASE_TREE_TTL = 3600;

    private const SYNC_FLAG_TTL = 600;

    public function count(): int
    {
        return NavMenuItem::query()->where('is_active', true)->count();
    }

    /** Đảm bảo menu mặc định luôn có đủ mục (tự heal khi thiếu). */
    public function ensureSynced(): void
    {
        Cache::remember('nav_menu:ensure_synced_ok', self::SYNC_FLAG_TTL, function () {
            if ($this->count() === 0 || ! $this->hasAllRegistryKeys()) {
                $this->syncFromRegistry();
            } elseif ($this->isStructureBroken()) {
                $this->repairStructureFromRegistry();
            }

            return true;
        });
    }

    public function invalidateCaches(): void
    {
        CatalogCache::bump(CatalogCache::BUCKET_NAV_MENU);
        Cache::forget('nav_menu:ensure_synced_ok');
    }

    public function hasAllRegistryKeys(): bool
    {
        $required = NavMenuRegistry::allItemKeys();
        $existing = NavMenuItem::query()
            ->where('is_active', true)
            ->whereIn('item_key', $required)
            ->pluck('item_key')
            ->all();

        return count(array_diff($required, $existing)) === 0;
    }

    /**
     * Đồng bộ menu mặc định: thêm mục thiếu, cập nhật metadata hệ thống.
     * Giữ nguyên label, sort_order, parent_id đã cấu hình.
     */
    public function syncFromRegistry(): void
    {
        DB::transaction(function () {
            $this->syncNodes(NavMenuRegistry::tree(), null);
            $this->deactivateOrphanItems();
            $this->applyStructure(NavMenuRegistry::tree(), null);
        });

        $this->invalidateCaches();
    }

    public function repairStructureFromRegistry(): void
    {
        $this->applyStructure(NavMenuRegistry::tree(), null);
        $this->invalidateCaches();
    }

    private function isStructureBroken(): bool
    {
        $expectedRoots = count(NavMenuRegistry::tree());
        $rootCount = NavMenuItem::query()->where('is_active', true)->whereNull('parent_id')->count();

        return $rootCount < $expectedRoots || $this->hasOrphanedActiveItems();
    }

    private function hasOrphanedActiveItems(): bool
    {
        $activeIds = NavMenuItem::query()->where('is_active', true)->pluck('id');

        return NavMenuItem::query()
            ->where('is_active', true)
            ->whereNotNull('parent_id')
            ->whereNotIn('parent_id', $activeIds)
            ->exists();
    }

    /**
     * Khôi phục parent_id theo registry — giữ nguyên label và sort_order.
     *
     * @param array<int, array<string, mixed>> $nodes
     */
    private function applyStructure(array $nodes, ?int $parentId): void
    {
        foreach ($nodes as $node) {
            $item = NavMenuItem::query()
                ->where('item_key', $node['item_key'])
                ->where('is_active', true)
                ->first();

            if (!$item) {
                continue;
            }

            $expectedParentId = ($item->is_dashboard ?? false) ? null : $parentId;

            if ((int) ($item->parent_id ?? 0) !== (int) ($expectedParentId ?? 0)) {
                $item->update(['parent_id' => $expectedParentId]);
            }

            if (!empty($node['children'])) {
                $this->applyStructure($node['children'], $item->id);
            }
        }
    }

    /** Ẩn mục cũ không còn trong registry (không xóa dữ liệu). */
    private function deactivateOrphanItems(): void
    {
        $validKeys = NavMenuRegistry::allItemKeys();

        NavMenuItem::query()
            ->where(function ($query) use ($validKeys) {
                $query->whereNull('item_key')
                    ->orWhereNotIn('item_key', $validKeys);
            })
            ->update(['is_active' => false]);
    }

    /** @deprecated Dùng syncFromRegistry() — không xóa dữ liệu. */
    public function seedFromRegistry(bool $force = false): void
    {
        if ($force && $this->count() === 0) {
            $this->syncFromRegistry();

            return;
        }

        $this->ensureSynced();
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     */
    private function syncNodes(array $nodes, ?int $parentId): void
    {
        foreach ($nodes as $index => $node) {
            $itemKey = (string) $node['item_key'];
            $item = NavMenuItem::query()->where('item_key', $itemKey)->first();

            if (!$item && !empty($node['path'])) {
                $pathname = explode('?', (string) $node['path'])[0];
                $item = NavMenuItem::query()
                    ->whereNull('item_key')
                    ->where(function ($query) use ($pathname, $node) {
                        $query->where('path', $node['path'])
                            ->orWhere('path', 'like', $pathname.'%');
                    })
                    ->first();
            }

            $sortOrder = (int) ($node['sort_order'] ?? $index * 10);

            if ($item) {
                $item->update([
                    'item_key' => $itemKey,
                    'permission_key' => $node['permission_key'] ?? $item->permission_key,
                    'permission_keys' => $node['permission_keys'] ?? $item->permission_keys,
                    'path' => $node['path'] ?? $item->path,
                    'icon' => $node['icon'] ?? $item->icon,
                    'is_dashboard' => (bool) ($node['is_dashboard'] ?? $item->is_dashboard),
                    'is_root_only' => (bool) ($node['is_root_only'] ?? $item->is_root_only),
                    'is_active' => true,
                ]);
            } else {
                $item = NavMenuItem::create([
                    'item_key' => $itemKey,
                    'parent_id' => $parentId,
                    'label' => $node['label'],
                    'path' => $node['path'] ?? null,
                    'icon' => $node['icon'] ?? null,
                    'permission_key' => $node['permission_key'] ?? null,
                    'permission_keys' => $node['permission_keys'] ?? null,
                    'sort_order' => $sortOrder,
                    'is_dashboard' => (bool) ($node['is_dashboard'] ?? false),
                    'is_root_only' => (bool) ($node['is_root_only'] ?? false),
                    'is_active' => true,
                ]);
            }

            if (!empty($node['children'])) {
                $this->syncNodes($node['children'], $item->id);
            }
        }
    }

    /** @return Collection<int, NavMenuItem> */
    public function allActiveItems(): Collection
    {
        return NavMenuItem::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /** @return array<int, array<string, mixed>> */
    public function treeForUser(User $user): array
    {
        $this->ensureSynced();

        $user->loadMissing('role');

        $permissionKeys = $user->permissionKeys();
        sort($permissionKeys);
        $cacheKey = sprintf(
            'user:%d:role:%s:root:%d:perm:%s',
            $user->id,
            (string) ($user->role_id ?? 0),
            RoleHierarchyHelper::isRootUser($user) ? 1 : 0,
            md5(implode(',', $permissionKeys)),
        );

        return CatalogCache::remember(
            CatalogCache::BUCKET_NAV_MENU,
            $cacheKey,
            self::USER_TREE_TTL,
            function () use ($user) {
                $items = $this->allActiveItems();
                $tree = $this->buildTree($items);

                if (RoleHierarchyHelper::isRootUser($user)) {
                    return $this->ensureDashboardFirst($tree);
                }

                return $this->ensureDashboardFirst($this->filterTree($tree, $user));
            },
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function adminTree(): array
    {
        $this->ensureSynced();

        return CatalogCache::remember(
            CatalogCache::BUCKET_NAV_MENU,
            'admin_tree',
            self::BASE_TREE_TTL,
            function () {
                $items = NavMenuItem::query()->where('is_active', true)->orderBy('sort_order')->get();

                return $this->buildTree($items);
            },
        );
    }

    /**
     * @param Collection<int, NavMenuItem> $items
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(Collection $items, ?int $parentId = null): array
    {
        return $items
            ->filter(function (NavMenuItem $item) use ($parentId) {
                if ($parentId === null) {
                    return $item->parent_id === null;
                }

                return (int) $item->parent_id === (int) $parentId;
            })
            ->sortBy('sort_order')
            ->values()
            ->map(fn (NavMenuItem $item) => $this->nodePayload($item, $this->buildTree($items, $item->id)))
            ->all();
    }

    /** @param array<int, array<string, mixed>> $children */
    private function nodePayload(NavMenuItem $item, array $children): array
    {
        return [
            'id' => $item->id,
            'itemKey' => $item->item_key,
            'parentId' => $item->parent_id,
            'label' => $item->label,
            'path' => $item->path,
            'icon' => $item->icon,
            'permissionKey' => $item->permission_key,
            'permissionKeys' => $item->permission_keys,
            'sortOrder' => $item->sort_order,
            'isDashboard' => $item->is_dashboard,
            'isRootOnly' => $item->is_root_only,
            'isActive' => $item->is_active,
            'children' => $children,
        ];
    }

    /** @param array<int, array<string, mixed>> $nodes */
    private function filterTree(array $nodes, User $user): array
    {
        $isRoot = RoleHierarchyHelper::isRootUser($user);
        $keys = $user->permissionKeys();

        $result = [];
        foreach ($nodes as $node) {
            if (($node['isRootOnly'] ?? false) && !$isRoot) {
                continue;
            }

            $children = $this->filterTree($node['children'] ?? [], $user);
            $hasPath = !empty($node['path']);
            $canSeeSelf = $hasPath
                ? $this->canAccessNode($node, $keys, $isRoot)
                : count($children) > 0;

            if (!$canSeeSelf) {
                continue;
            }

            $node['children'] = $children;
            $result[] = $node;
        }

        return $result;
    }

    /** @param array<string, mixed> $node */
    private function canAccessNode(array $node, array $keys, bool $isRoot): bool
    {
        if (($node['isRootOnly'] ?? false) && $isRoot) {
            return true;
        }

        $alternatives = $node['permissionKeys'] ?? null;
        if (is_array($alternatives) && count($alternatives) > 0) {
            return count(array_intersect($alternatives, $keys)) > 0;
        }

        $permission = $node['permissionKey'] ?? null;
        if (!$permission) {
            return true;
        }

        return in_array($permission, $keys, true);
    }

    /** @param array<int, array<string, mixed>> $nodes */
    private function ensureDashboardFirst(array $nodes): array
    {
        $dashboard = [];
        $others = [];

        foreach ($nodes as $node) {
            if ($node['isDashboard'] ?? false) {
                $dashboard[] = $node;
            } else {
                $others[] = $node;
            }
        }

        return array_merge($dashboard, $others);
    }

    /**
     * Chỉ cập nhật tên và vị trí sắp xếp — không xóa mục menu.
     *
     * @param array<int, array{id: int, parentId?: int|null, sortOrder: int, label?: string}> $items
     */
    public function reorder(User $user, array $items): void
    {
        if (!RoleHierarchyHelper::isRootUser($user)) {
            throw ValidationException::withMessages([
                'items' => 'Chỉ tài khoản ROOT mới được cấu hình menu.',
            ]);
        }

        $this->ensureSynced();

        $existing = NavMenuItem::query()->where('is_active', true)->get()->keyBy('id');
        if ($existing->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Menu chưa được khởi tạo.',
            ]);
        }

        $submittedIds = collect($items)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count($submittedIds) !== $existing->count()) {
            throw ValidationException::withMessages([
                'items' => 'Không được xóa mục menu. Chỉ được sửa tên và thứ tự.',
            ]);
        }

        $dashboardIds = $existing->where('is_dashboard', true)->pluck('id')->all();
        $rootItems = collect($items)->filter(fn (array $row) => empty($row['parentId']));
        $rootSorted = $rootItems->sortBy('sortOrder')->values();

        if (count($dashboardIds) > 0) {
            $firstRootId = $rootSorted->first()['id'] ?? null;
            if (!in_array($firstRootId, $dashboardIds, true)) {
                throw ValidationException::withMessages([
                    'items' => 'Dashboard phải luôn đứng đầu menu.',
                ]);
            }
        }

        DB::transaction(function () use ($items, $existing) {
            foreach ($items as $row) {
                $id = (int) $row['id'];
                if (!$existing->has($id)) {
                    continue;
                }

                /** @var NavMenuItem $model */
                $model = $existing->get($id);
                $label = isset($row['label']) ? trim((string) $row['label']) : null;

                if ($model->is_dashboard) {
                    $updates = [
                        'parent_id' => null,
                        'sort_order' => 0,
                    ];
                    if ($label !== null && $label !== '') {
                        $updates['label'] = $label;
                    }
                    $model->update($updates);
                    continue;
                }

                $updates = [
                    'parent_id' => $row['parentId'] ?? null,
                    'sort_order' => (int) $row['sortOrder'],
                ];
                if ($label !== null && $label !== '') {
                    $updates['label'] = $label;
                }
                $model->update($updates);
            }
        });

        $this->invalidateCaches();
    }
}
