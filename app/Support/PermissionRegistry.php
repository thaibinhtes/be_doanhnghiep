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
            // Menu — Tổng quan
            ['key' => 'menu.dashboard', 'name' => 'Dashboard', 'type' => 'menu', 'group_name' => 'Tổng quan', 'path' => '/dashboard', 'sort_order' => 1],
            ['key' => 'menu.import-history', 'name' => 'Danh mục lịch sử', 'type' => 'menu', 'group_name' => 'Tổng quan', 'path' => '/admin/import-history', 'sort_order' => 2],

            // Menu — Doanh nghiệp
            ['key' => 'menu.companies.list', 'name' => 'Danh sách doanh nghiệp', 'type' => 'menu', 'group_name' => 'Quản lý doanh nghiệp', 'path' => '/companies', 'sort_order' => 10],
            ['key' => 'menu.companies.map', 'name' => 'Bản đồ doanh nghiệp', 'type' => 'menu', 'group_name' => 'Quản lý doanh nghiệp', 'path' => '/companies/map', 'sort_order' => 11],
            ['key' => 'menu.companies.identity', 'name' => 'Định danh doanh nghiệp', 'type' => 'menu', 'group_name' => 'Quản lý doanh nghiệp', 'path' => '/companies/identity', 'sort_order' => 12],
            ['key' => 'menu.companies.create', 'name' => 'Tạo doanh nghiệp', 'type' => 'menu', 'group_name' => 'Quản lý doanh nghiệp', 'path' => '/companies/create', 'sort_order' => 13],
            ['key' => 'menu.companies.statuses', 'name' => 'Trạng thái doanh nghiệp', 'type' => 'menu', 'group_name' => 'Quản lý doanh nghiệp', 'path' => '/companies/statuses', 'sort_order' => 14],
            ['key' => 'menu.cooperatives.list', 'name' => 'Hợp tác xã', 'type' => 'menu', 'group_name' => 'Quản lý doanh nghiệp', 'path' => '/cooperatives', 'sort_order' => 15],

            // Menu — Thành viên
            ['key' => 'menu.members.list', 'name' => 'Danh sách thành viên', 'type' => 'menu', 'group_name' => 'Thành viên', 'path' => '/members', 'sort_order' => 20],
            ['key' => 'menu.members.create', 'name' => 'Tạo thành viên', 'type' => 'menu', 'group_name' => 'Thành viên', 'path' => '/members/create', 'sort_order' => 21],

            // Menu — Báo cáo
            ['key' => 'menu.reports.summary', 'name' => 'Báo cáo tổng hợp', 'type' => 'menu', 'group_name' => 'Báo cáo', 'path' => '/reports/summary', 'sort_order' => 25],
            ['key' => 'menu.reports.progress', 'name' => 'Báo cáo tiến độ định danh', 'type' => 'menu', 'group_name' => 'Báo cáo', 'path' => '/reports/progress', 'sort_order' => 26],
            ['key' => 'menu.reports.identity-history', 'name' => 'Lịch sử định danh doanh nghiệp', 'type' => 'menu', 'group_name' => 'Báo cáo', 'path' => '/reports/identity-history', 'sort_order' => 27],

            // Menu — Hệ thống
            ['key' => 'menu.admin.roles', 'name' => 'Phân quyền', 'type' => 'menu', 'group_name' => 'Hệ thống', 'path' => '/admin/roles', 'sort_order' => 30],
            ['key' => 'menu.admin.cadastral', 'name' => 'Quản lý địa chính', 'type' => 'menu', 'group_name' => 'Hệ thống', 'path' => '/admin/cadastral', 'sort_order' => 31],
            ['key' => 'menu.admin.business-types', 'name' => 'Loại hình doanh nghiệp', 'type' => 'menu', 'group_name' => 'Hệ thống', 'path' => '/admin/business-types', 'sort_order' => 32],
            ['key' => 'menu.admin.industry-categories', 'name' => 'Danh mục ngành nghề', 'type' => 'menu', 'group_name' => 'Hệ thống', 'path' => '/admin/industry-categories', 'sort_order' => 33],
            ['key' => 'menu.admin.org-units', 'name' => 'Quản lý đơn vị', 'type' => 'menu', 'group_name' => 'Hệ thống', 'path' => '/admin/org-units', 'sort_order' => 34],
            ['key' => 'menu.admin.users', 'name' => 'Quản lý người dùng', 'type' => 'menu', 'group_name' => 'Hệ thống', 'path' => '/admin/users', 'sort_order' => 35],

            // Feature — Doanh nghiệp
            ['key' => 'feature.companies.create', 'name' => 'Tạo doanh nghiệp', 'type' => 'feature', 'group_name' => 'Doanh nghiệp', 'path' => null, 'sort_order' => 100],
            ['key' => 'feature.companies.edit', 'name' => 'Sửa doanh nghiệp', 'type' => 'feature', 'group_name' => 'Doanh nghiệp', 'path' => null, 'sort_order' => 101],
            ['key' => 'feature.companies.delete', 'name' => 'Xóa doanh nghiệp', 'type' => 'feature', 'group_name' => 'Doanh nghiệp', 'path' => null, 'sort_order' => 102],
            ['key' => 'feature.companies.export', 'name' => 'Xuất Excel doanh nghiệp', 'type' => 'feature', 'group_name' => 'Doanh nghiệp', 'path' => null, 'sort_order' => 103],
            ['key' => 'feature.companies.import', 'name' => 'Nhập Excel doanh nghiệp', 'type' => 'feature', 'group_name' => 'Doanh nghiệp', 'path' => null, 'sort_order' => 104],
            ['key' => 'feature.companies.dinh-danh', 'name' => 'Cập nhật định danh', 'type' => 'feature', 'group_name' => 'Doanh nghiệp', 'path' => null, 'sort_order' => 105],
            ['key' => 'feature.companies.map', 'name' => 'Cập nhật bản đồ', 'type' => 'feature', 'group_name' => 'Doanh nghiệp', 'path' => null, 'sort_order' => 106],
            ['key' => 'feature.cooperatives.export', 'name' => 'Xuất Excel hợp tác xã', 'type' => 'feature', 'group_name' => 'Hợp tác xã', 'path' => null, 'sort_order' => 108],
            ['key' => 'feature.cooperatives.import', 'name' => 'Nhập Excel hợp tác xã', 'type' => 'feature', 'group_name' => 'Hợp tác xã', 'path' => null, 'sort_order' => 109],
            ['key' => 'feature.reports.export', 'name' => 'Xuất báo cáo tổng hợp', 'type' => 'feature', 'group_name' => 'Báo cáo', 'path' => null, 'sort_order' => 107],

            // Feature — Thành viên
            ['key' => 'feature.members.create', 'name' => 'Tạo thành viên', 'type' => 'feature', 'group_name' => 'Thành viên', 'path' => null, 'sort_order' => 110],
            ['key' => 'feature.members.edit', 'name' => 'Sửa thành viên', 'type' => 'feature', 'group_name' => 'Thành viên', 'path' => null, 'sort_order' => 111],
            ['key' => 'feature.members.delete', 'name' => 'Xóa thành viên', 'type' => 'feature', 'group_name' => 'Thành viên', 'path' => null, 'sort_order' => 112],

            // Feature — Hệ thống
            ['key' => 'feature.roles.manage', 'name' => 'Quản lý phân quyền', 'type' => 'feature', 'group_name' => 'Hệ thống', 'path' => null, 'sort_order' => 120],
            ['key' => 'feature.statuses.manage', 'name' => 'Quản lý trạng thái doanh nghiệp', 'type' => 'feature', 'group_name' => 'Hệ thống', 'path' => null, 'sort_order' => 121],
            ['key' => 'feature.cadastral.manage', 'name' => 'Quản lý mapping hành chính', 'type' => 'feature', 'group_name' => 'Hệ thống', 'path' => null, 'sort_order' => 122],
            ['key' => 'feature.business-types.manage', 'name' => 'Quản lý loại hình doanh nghiệp', 'type' => 'feature', 'group_name' => 'Hệ thống', 'path' => null, 'sort_order' => 123],
            ['key' => 'feature.industry-categories.manage', 'name' => 'Quản lý danh mục ngành nghề', 'type' => 'feature', 'group_name' => 'Hệ thống', 'path' => null, 'sort_order' => 124],
            ['key' => 'feature.industry-categories.sync', 'name' => 'Đồng bộ danh mục ngành nghề (ROOT)', 'type' => 'feature', 'group_name' => 'Hệ thống', 'path' => null, 'sort_order' => 128],
            ['key' => 'feature.org-units.manage', 'name' => 'Quản lý đơn vị', 'type' => 'feature', 'group_name' => 'Hệ thống', 'path' => null, 'sort_order' => 125],
            ['key' => 'feature.org-units.view', 'name' => 'Xem dữ liệu theo đơn vị', 'type' => 'feature', 'group_name' => 'Hệ thống', 'path' => null, 'sort_order' => 126],
            ['key' => 'feature.users.manage', 'name' => 'Quản lý người dùng', 'type' => 'feature', 'group_name' => 'Hệ thống', 'path' => null, 'sort_order' => 127],
        ];
    }

    public static function allKeys(): array
    {
        return array_column(self::all(), 'key');
    }

    public static function viewerKeys(): array
    {
        return [
            'menu.dashboard',
            'menu.companies.list',
            'menu.companies.map',
            'menu.companies.identity',
            'menu.reports.summary',
            'menu.reports.progress',
            'menu.reports.identity-history',
            'menu.members.list',
            'menu.cooperatives.list',
            'menu.import-history',
            'feature.org-units.view',
        ];
    }

    public static function editorKeys(): array
    {
        return array_values(array_filter(
            self::allKeys(),
            fn (string $key) => !str_starts_with($key, 'menu.admin.')
                && $key !== 'feature.roles.manage'
                && $key !== 'feature.industry-categories.sync'
        ));
    }
}
