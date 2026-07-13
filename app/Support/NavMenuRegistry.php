<?php

namespace App\Support;

/**
 * Canonical navigation tree — nguồn mặc định để đồng bộ nav_menu_items.
 * Chỉ bổ sung mục thiếu; không xóa mục đã có. Tên và thứ tự do ROOT cấu hình được giữ nguyên.
 */
class NavMenuRegistry
{
    public static function tree(): array
    {
        return [
            [
                'item_key' => 'dashboard',
                'label' => 'Dashboard',
                'path' => '/dashboard',
                'icon' => 'LayoutDashboardIcon',
                'permission_key' => 'menu.dashboard',
                'is_dashboard' => true,
                'sort_order' => 0,
            ],
            [
                'item_key' => 'cat.companies',
                'label' => 'Quản lý doanh nghiệp',
                'icon' => 'TableIcon',
                'sort_order' => 10,
                'children' => [
                    ['item_key' => 'link.companies.list', 'label' => 'Doanh nghiệp', 'path' => '/companies', 'permission_key' => 'menu.companies.list'],
                    ['item_key' => 'link.companies.create', 'label' => 'Tạo mới doanh nghiệp', 'path' => '/companies/create', 'permission_key' => 'menu.companies.create'],
                    [
                        'item_key' => 'link.companies.import',
                        'label' => 'Import doanh nghiệp',
                        'path' => '/companies/import',
                        'permission_key' => 'feature.companies.import',
                        'permission_keys' => ['feature.companies.import', 'menu.companies.list'],
                    ],
                    ['item_key' => 'link.companies.import-tax', 'label' => 'Cập nhật tình trạng hoạt động doanh nghiệp', 'path' => '/companies/import-tax', 'permission_key' => 'menu.admin.org-units'],
                    ['item_key' => 'link.companies.tax-status', 'label' => 'Tình trạng hoạt động doanh nghiệp', 'path' => '/admin/tax-management?tab=companies', 'permission_key' => 'menu.admin.org-units'],
                    ['item_key' => 'link.companies.statuses', 'label' => 'Trạng thái doanh nghiệp', 'path' => '/companies/statuses', 'permission_key' => 'menu.companies.statuses'],
                ],
            ],
            [
                'item_key' => 'cat.cooperatives',
                'label' => 'Quản lý hợp tác xã',
                'icon' => 'BoxCubeIcon',
                'sort_order' => 20,
                'children' => [
                    [
                        'item_key' => 'link.cooperatives.list',
                        'label' => 'Hợp tác xã',
                        'path' => '/cooperatives',
                        'permission_key' => 'menu.cooperatives.list',
                        'permission_keys' => ['menu.cooperatives.list', 'menu.companies.list'],
                    ],
                    [
                        'item_key' => 'link.cooperatives.create',
                        'label' => 'Thêm mới hợp tác xã',
                        'path' => '/cooperatives/create',
                        'permission_key' => 'menu.cooperatives.list',
                        'permission_keys' => ['menu.cooperatives.list', 'menu.companies.list'],
                    ],
                    [
                        'item_key' => 'link.cooperatives.import',
                        'label' => 'Import hợp tác xã',
                        'path' => '/cooperatives/import',
                        'permission_key' => 'feature.cooperatives.import',
                        'permission_keys' => ['feature.cooperatives.import', 'menu.cooperatives.list', 'menu.companies.list'],
                    ],
                    ['item_key' => 'link.cooperatives.import-tax', 'label' => 'Cập nhật tình trạng hoạt động HTX', 'path' => '/cooperatives/import-tax', 'permission_key' => 'menu.admin.org-units'],
                    ['item_key' => 'link.cooperatives.tax', 'label' => 'Tình trạng hoạt động HTX', 'path' => '/cooperatives/tax', 'permission_key' => 'menu.admin.org-units'],
                    ['item_key' => 'link.cooperatives.members', 'label' => 'Thành viên hợp tác xã', 'path' => '/cooperatives/members', 'permission_key' => 'menu.members.list'],
                ],
            ],
            [
                'item_key' => 'cat.members',
                'label' => 'Thành viên',
                'icon' => 'UserGroupIcon',
                'sort_order' => 25,
                'children' => [
                    ['item_key' => 'link.members.list', 'label' => 'Danh sách thành viên', 'path' => '/members', 'permission_key' => 'menu.members.list'],
                    ['item_key' => 'link.members.create', 'label' => 'Tạo thành viên', 'path' => '/members/create', 'permission_key' => 'menu.members.create'],
                ],
            ],
            [
                'item_key' => 'cat.identity',
                'label' => 'Định danh tổ chức',
                'icon' => 'GridIcon',
                'sort_order' => 30,
                'children' => [
                    ['item_key' => 'link.companies.map', 'label' => 'Bản đồ số', 'path' => '/companies/map', 'permission_key' => 'menu.companies.map'],
                    ['item_key' => 'link.companies.identity', 'label' => 'Định danh', 'path' => '/companies/identity', 'permission_key' => 'menu.companies.identity'],
                ],
            ],
            [
                'item_key' => 'cat.reports',
                'label' => 'Báo cáo - thống kê',
                'icon' => 'PieChartIcon',
                'sort_order' => 40,
                'children' => [
                    ['item_key' => 'link.reports.summary', 'label' => 'Báo cáo tổng hợp', 'path' => '/reports/summary', 'permission_key' => 'menu.reports.summary'],
                    ['item_key' => 'link.reports.progress', 'label' => 'Báo cáo tiến độ', 'path' => '/reports/progress', 'permission_key' => 'menu.reports.progress'],
                    ['item_key' => 'link.reports.identity-history', 'label' => 'Lịch sử định danh doanh nghiệp', 'path' => '/reports/identity-history', 'permission_key' => 'menu.reports.identity-history'],
                    ['item_key' => 'link.companies.import-history', 'label' => 'Lịch sử import doanh nghiệp', 'path' => '/companies/import-history', 'permission_key' => 'menu.import-history'],
                    ['item_key' => 'link.cooperatives.import-history', 'label' => 'Lịch sử import hợp tác xã', 'path' => '/cooperatives/import-history', 'permission_key' => 'menu.import-history'],
                    ['item_key' => 'link.admin.import-history', 'label' => 'Danh mục lịch sử import', 'path' => '/admin/import-history', 'permission_key' => 'menu.import-history'],
                ],
            ],
            [
                'item_key' => 'cat.system',
                'label' => 'Hệ thống và danh mục',
                'icon' => 'SettingsIcon',
                'sort_order' => 50,
                'children' => [
                    [
                        'item_key' => 'folder.cadastral',
                        'label' => 'Danh mục đơn vị hành chính',
                        'permission_key' => 'menu.admin.cadastral',
                        'children' => [
                            ['item_key' => 'link.cadastral.legacy', 'label' => 'Đơn vị hành chính cũ', 'path' => '/admin/cadastral?tab=legacy', 'permission_key' => 'menu.admin.cadastral'],
                            ['item_key' => 'link.cadastral.new', 'label' => 'Đơn vị hành chính mới', 'path' => '/admin/cadastral?tab=new', 'permission_key' => 'menu.admin.cadastral'],
                            ['item_key' => 'link.cadastral.mapping', 'label' => 'Ánh xạ đơn vị hành chính', 'path' => '/admin/cadastral?tab=mapping', 'permission_key' => 'menu.admin.cadastral'],
                        ],
                    ],
                    ['item_key' => 'link.industry-categories', 'label' => 'Danh mục ngành nghề', 'path' => '/admin/industry-categories', 'permission_key' => 'menu.admin.industry-categories'],
                    ['item_key' => 'link.business-types', 'label' => 'Danh mục loại hình doanh nghiệp', 'path' => '/admin/business-types', 'permission_key' => 'menu.admin.business-types'],
                    ['item_key' => 'link.cooperative-business-types', 'label' => 'Danh mục loại hình hợp tác xã', 'path' => '/admin/cooperative-business-types', 'permission_key' => 'menu.admin.business-types'],
                    ['item_key' => 'link.org-units', 'label' => 'Danh mục đơn vị', 'path' => '/admin/org-units', 'permission_key' => 'menu.admin.org-units'],
                    ['item_key' => 'link.users', 'label' => 'Danh mục người dùng', 'path' => '/admin/users', 'permission_key' => 'menu.admin.users'],
                    ['item_key' => 'link.tax-units', 'label' => 'Danh mục thuế', 'path' => '/admin/tax-management?tab=tax-units', 'permission_key' => 'menu.admin.tax-units'],
                    ['item_key' => 'link.import-mapping-companies', 'label' => 'Cấu hình format ánh xạ import DN', 'path' => '/admin/import-mapping/companies', 'permission_key' => 'menu.admin.import-mapping-companies'],
                    ['item_key' => 'link.import-mapping-cooperatives', 'label' => 'Cấu hình format ánh xạ import HTX', 'path' => '/admin/import-mapping/cooperatives', 'permission_key' => 'menu.admin.import-mapping-cooperatives'],
                    ['item_key' => 'link.roles', 'label' => 'Phân quyền', 'path' => '/admin/roles', 'permission_key' => 'menu.admin.roles'],
                    [
                        'item_key' => 'link.menu-config',
                        'label' => 'Cấu hình menu',
                        'path' => '/admin/menu-config',
                        'permission_key' => null,
                        'is_root_only' => true,
                    ],
                ],
            ],
        ];
    }

    /** @return list<string> */
    public static function allItemKeys(): array
    {
        $keys = [];
        $walk = function (array $nodes) use (&$walk, &$keys): void {
            foreach ($nodes as $node) {
                if (!empty($node['item_key'])) {
                    $keys[] = $node['item_key'];
                }
                if (!empty($node['children'])) {
                    $walk($node['children']);
                }
            }
        };
        $walk(self::tree());

        return $keys;
    }
}
