<?php

namespace App\Support;

use App\Models\NavMenuItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NavMenuService
{
    public function seedFromRegistry(bool $force = false): void
    {
        if (!$force && NavMenuItem::query()->exists()) {
            return;
        }

        DB::transaction(function () {
            NavMenuItem::query()->delete();

            $sort = 0;
            foreach (NavMenuRegistry::tree() as $node) {
                $this->createNode($node, null, $node['sort_order'] ?? $sort);
                $sort += 10;
            }
        });
    }

    /** @param array<string, mixed> $node */
    private function createNode(array $node, ?int $parentId, int $sortOrder): NavMenuItem
    {
        $item = NavMenuItem::create([
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

        $childSort = 0;
        foreach ($node['children'] ?? [] as $child) {
            $this->createNode($child, $item->id, $childSort);
            $childSort += 10;
        }

        return $item;
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
        $items = $this->allActiveItems();
        $tree = $this->buildTree($items);
        $filtered = $this->filterTree($tree, $user);

        return $this->ensureDashboardFirst($filtered);
    }

    /** @return array<int, array<string, mixed>> */
    public function adminTree(): array
    {
        $items = NavMenuItem::query()->orderBy('sort_order')->get();

        return $this->buildTree($items);
    }

    /**
     * @param Collection<int, NavMenuItem> $items
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(Collection $items, ?int $parentId = null): array
    {
        return $items
            ->where('parent_id', $parentId)
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
     * @param array<int, array{id: int, parentId?: int|null, sortOrder: int, label?: string}> $items
     */
    public function reorder(User $user, array $items): void
    {
        if (!RoleHierarchyHelper::isRootUser($user)) {
            throw ValidationException::withMessages([
                'items' => 'Chỉ tài khoản ROOT mới được cấu hình menu.',
            ]);
        }

        $existing = NavMenuItem::query()->get()->keyBy('id');
        if ($existing->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Menu chưa được khởi tạo.',
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
    }
}
