<?php

namespace App\Support;

class PermissionRegistry
{
    /**
     * All system permissions: menu access + feature actions.
     *
     * @return array<int, array{key: string, name: string, type: string, group_name: string, path?: string|null, sort_order: int}>
     */
    public static function all(): array
    {
        return [
            // Menu — Doanh nghiệp
            ['key' => 'menu.companies.list', 'name' => 'Danh sách doanh nghiệp', 'type' => 'menu', 'group_name' => 'Quản lý doanh nghiệp', 'path' => '/companies', 'sort_order' => 10],
            ['key' => 'menu.companies.map', 'name' => 'Bản đồ doanh nghiệp', 'type' => 'menu', 'group_name' => 'Quản lý doanh nghiệp', 'path' => '/companies/map', 'sort_order' => 11],
            ['key' => 'menu.companies.identity', 'name' => 'Định danh doanh nghiệp', 'type' => 'menu', 'group_name' => 'Quản lý doanh nghiệp', 'path' => '/companies/identity', 'sort_order' => 12],
            ['key' => 'menu.companies.create', 'name' => 'Tạo doanh nghiệp', 'type' => 'menu', 'group_name' => 'Quản lý doanh nghiệp', 'path' => '/companies/create', 'sort_order' => 13],
            ['key' => 'menu.companies.statuses', 'name' => 'Trạng thái doanh nghiệp', 'type' => 'menu', 'group_name' => 'Quản lý doanh nghiệp', 'path' => '/companies/statuses', 'sort_order' => 14],

            // Menu — Thành viên
            ['key' => 'menu.members.list', 'name' => 'Danh sách thành viên', 'type' => 'menu', 'group_name' => 'Thành viên', 'path' => '/members', 'sort_order' => 20],
            ['key' => 'menu.members.create', 'name' => 'Tạo thành viên', 'type' => 'menu', 'group_name' => 'Thành viên', 'path' => '/members/create', 'sort_order' => 21],

            // Menu — Báo cáo
            ['key' => 'menu.reports.summary', 'name' => 'Báo cáo tổng hợp', 'type' => 'menu', 'group_name' => 'Báo cáo', 'path' => '/reports/summary', 'sort_order' => 25],
            ['key' => 'menu.reports.progress', 'name' => 'Báo cáo tiến độ định danh', 'type' => 'menu', 'group_name' => 'Báo cáo', 'path' => '/reports/progress', 'sort_order' => 26],

            // Menu — Hệ thống
            ['key' => 'menu.admin.roles', 'name' => 'Phân quyền', 'type' => 'menu', 'group_name' => 'Hệ thống', 'path' => '/admin/roles', 'sort_order' => 30],

            // Feature — Doanh nghiệp
            ['key' => 'feature.companies.create', 'name' => 'Tạo doanh nghiệp', 'type' => 'feature', 'group_name' => 'Doanh nghiệp', 'path' => null, 'sort_order' => 100],
            ['key' => 'feature.companies.edit', 'name' => 'Sửa doanh nghiệp', 'type' => 'feature', 'group_name' => 'Doanh nghiệp', 'path' => null, 'sort_order' => 101],
            ['key' => 'feature.companies.delete', 'name' => 'Xóa doanh nghiệp', 'type' => 'feature', 'group_name' => 'Doanh nghiệp', 'path' => null, 'sort_order' => 102],
            ['key' => 'feature.companies.export', 'name' => 'Xuất Excel doanh nghiệp', 'type' => 'feature', 'group_name' => 'Doanh nghiệp', 'path' => null, 'sort_order' => 103],
            ['key' => 'feature.companies.import', 'name' => 'Nhập Excel doanh nghiệp', 'type' => 'feature', 'group_name' => 'Doanh nghiệp', 'path' => null, 'sort_order' => 104],
            ['key' => 'feature.companies.dinh-danh', 'name' => 'Cập nhật định danh', 'type' => 'feature', 'group_name' => 'Doanh nghiệp', 'path' => null, 'sort_order' => 105],
            ['key' => 'feature.companies.map', 'name' => 'Cập nhật bản đồ', 'type' => 'feature', 'group_name' => 'Doanh nghiệp', 'path' => null, 'sort_order' => 106],
            ['key' => 'feature.reports.export', 'name' => 'Xuất báo cáo tổng hợp', 'type' => 'feature', 'group_name' => 'Báo cáo', 'path' => null, 'sort_order' => 107],

            // Feature — Thành viên
            ['key' => 'feature.members.create', 'name' => 'Tạo thành viên', 'type' => 'feature', 'group_name' => 'Thành viên', 'path' => null, 'sort_order' => 110],
            ['key' => 'feature.members.edit', 'name' => 'Sửa thành viên', 'type' => 'feature', 'group_name' => 'Thành viên', 'path' => null, 'sort_order' => 111],
            ['key' => 'feature.members.delete', 'name' => 'Xóa thành viên', 'type' => 'feature', 'group_name' => 'Thành viên', 'path' => null, 'sort_order' => 112],

            // Feature — Hệ thống
            ['key' => 'feature.roles.manage', 'name' => 'Quản lý phân quyền', 'type' => 'feature', 'group_name' => 'Hệ thống', 'path' => null, 'sort_order' => 120],
            ['key' => 'feature.statuses.manage', 'name' => 'Quản lý trạng thái doanh nghiệp', 'type' => 'feature', 'group_name' => 'Hệ thống', 'path' => null, 'sort_order' => 121],
        ];
    }

    public static function allKeys(): array
    {
        return array_column(self::all(), 'key');
    }

    public static function viewerKeys(): array
    {
        return [
            'menu.companies.list',
            'menu.companies.map',
            'menu.companies.identity',
            'menu.reports.summary',
            'menu.reports.progress',
            'menu.members.list',
        ];
    }

    public static function editorKeys(): array
    {
        return array_values(array_filter(
            self::allKeys(),
            fn (string $key) => !str_starts_with($key, 'menu.admin.') && $key !== 'feature.roles.manage'
        ));
    }
}
