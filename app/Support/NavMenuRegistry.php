<?php

namespace App\Support;

/**
 * Canonical navigation tree used to seed and reset nav_menu_items.
 *
 * @return array<int, array<string, mixed>>
 */
class NavMenuRegistry
{
    public static function tree(): array
    {
        return [
            [
                'label' => 'Dashboard',
                'path' => '/dashboard',
                'icon' => 'LayoutDashboardIcon',
                'permission_key' => 'menu.dashboard',
                'is_dashboard' => true,
                'sort_order' => 0,
            ],
            [
                'label' => 'Quản lý doanh nghiệp',
                'icon' => 'TableIcon',
                'sort_order' => 10,
                'children' => [
                    ['label' => 'Doanh nghiệp', 'path' => '/companies', 'permission_key' => 'menu.companies.list'],
                    ['label' => 'Tạo mới doanh nghiệp', 'path' => '/companies/create', 'permission_key' => 'menu.companies.create'],
                    [
                        'label' => 'Import doanh nghiệp',
                        'path' => '/companies/import',
                        'permission_key' => 'feature.companies.import',
                        'permission_keys' => ['feature.companies.import', 'menu.companies.list'],
                    ],
                    ['label' => 'Cập nhật tình trạng hoạt động doanh nghiệp', 'path' => '/companies/import-tax', 'permission_key' => 'menu.admin.org-units'],
                    ['label' => 'Tình trạng hoạt động doanh nghiệp', 'path' => '/admin/tax-management?tab=companies', 'permission_key' => 'menu.admin.org-units'],
                    ['label' => 'Trạng thái doanh nghiệp', 'path' => '/companies/statuses', 'permission_key' => 'menu.companies.statuses'],
                ],
            ],
            [
                'label' => 'Quản lý hợp tác xã',
                'icon' => 'BoxCubeIcon',
                'sort_order' => 20,
                'children' => [
                    [
                        'label' => 'Hợp tác xã',
                        'path' => '/cooperatives',
                        'permission_key' => 'menu.cooperatives.list',
                        'permission_keys' => ['menu.cooperatives.list', 'menu.companies.list'],
                    ],
                    [
                        'label' => 'Thêm mới hợp tác xã',
                        'path' => '/cooperatives/create',
                        'permission_key' => 'menu.cooperatives.list',
                        'permission_keys' => ['menu.cooperatives.list', 'menu.companies.list'],
                    ],
                    [
                        'label' => 'Import hợp tác xã',
                        'path' => '/cooperatives/import',
                        'permission_key' => 'feature.cooperatives.import',
                        'permission_keys' => ['feature.cooperatives.import', 'menu.cooperatives.list', 'menu.companies.list'],
                    ],
                    ['label' => 'Cập nhật tình trạng hoạt động HTX', 'path' => '/cooperatives/import-tax', 'permission_key' => 'menu.admin.org-units'],
                    ['label' => 'Tình trạng hoạt động HTX', 'path' => '/cooperatives/tax', 'permission_key' => 'menu.admin.org-units'],
                    ['label' => 'Thành viên hợp tác xã', 'path' => '/cooperatives/members', 'permission_key' => 'menu.members.list'],
                ],
            ],
            [
                'label' => 'Thành viên',
                'icon' => 'UserGroupIcon',
                'sort_order' => 25,
                'children' => [
                    ['label' => 'Danh sách thành viên', 'path' => '/members', 'permission_key' => 'menu.members.list'],
                    ['label' => 'Tạo thành viên', 'path' => '/members/create', 'permission_key' => 'menu.members.create'],
                ],
            ],
            [
                'label' => 'Định danh tổ chức',
                'icon' => 'GridIcon',
                'sort_order' => 30,
                'children' => [
                    ['label' => 'Bản đồ số', 'path' => '/companies/map', 'permission_key' => 'menu.companies.map'],
                    ['label' => 'Định danh', 'path' => '/companies/identity', 'permission_key' => 'menu.companies.identity'],
                ],
            ],
            [
                'label' => 'Báo cáo - thống kê',
                'icon' => 'PieChartIcon',
                'sort_order' => 40,
                'children' => [
                    ['label' => 'Báo cáo tổng hợp', 'path' => '/reports/summary', 'permission_key' => 'menu.reports.summary'],
                    ['label' => 'Báo cáo tiến độ', 'path' => '/reports/progress', 'permission_key' => 'menu.reports.progress'],
                    ['label' => 'Lịch sử định danh doanh nghiệp', 'path' => '/reports/identity-history', 'permission_key' => 'menu.reports.identity-history'],
                    ['label' => 'Lịch sử import doanh nghiệp', 'path' => '/companies/import-history', 'permission_key' => 'menu.import-history'],
                    ['label' => 'Lịch sử import hợp tác xã', 'path' => '/cooperatives/import-history', 'permission_key' => 'menu.import-history'],
                    ['label' => 'Danh mục lịch sử import', 'path' => '/admin/import-history', 'permission_key' => 'menu.import-history'],
                ],
            ],
            [
                'label' => 'Hệ thống và danh mục',
                'icon' => 'SettingsIcon',
                'sort_order' => 50,
                'children' => [
                    [
                        'label' => 'Danh mục đơn vị hành chính',
                        'permission_key' => 'menu.admin.cadastral',
                        'children' => [
                            ['label' => 'Đơn vị hành chính cũ', 'path' => '/admin/cadastral?tab=legacy', 'permission_key' => 'menu.admin.cadastral'],
                            ['label' => 'Đơn vị hành chính mới', 'path' => '/admin/cadastral?tab=new', 'permission_key' => 'menu.admin.cadastral'],
                            ['label' => 'Ánh xạ đơn vị hành chính', 'path' => '/admin/cadastral?tab=mapping', 'permission_key' => 'menu.admin.cadastral'],
                        ],
                    ],
                    ['label' => 'Danh mục ngành nghề', 'path' => '/admin/industry-categories', 'permission_key' => 'menu.admin.industry-categories'],
                    ['label' => 'Danh mục loại hình doanh nghiệp', 'path' => '/admin/business-types', 'permission_key' => 'menu.admin.business-types'],
                    ['label' => 'Danh mục loại hình hợp tác xã', 'path' => '/admin/cooperative-business-types', 'permission_key' => 'menu.admin.business-types'],
                    ['label' => 'Danh mục đơn vị', 'path' => '/admin/org-units', 'permission_key' => 'menu.admin.org-units'],
                    ['label' => 'Danh mục người dùng', 'path' => '/admin/users', 'permission_key' => 'menu.admin.users'],
                    ['label' => 'Danh mục thuế', 'path' => '/admin/tax-management?tab=tax-units', 'permission_key' => 'menu.admin.tax-units'],
                    ['label' => 'Phân quyền', 'path' => '/admin/roles', 'permission_key' => 'menu.admin.roles'],
                    [
                        'label' => 'Cấu hình menu',
                        'path' => '/admin/menu-config',
                        'permission_key' => null,
                        'is_root_only' => true,
                    ],
                ],
            ],
        ];
    }

    /** @return array<int, array{path: string, permission: string|array<int, string>}> */
    public static function routePermissionMap(): array
    {
        $map = [
            ['path' => '/', 'permission' => 'menu.dashboard'],
            ['path' => '/companies/:id/map', 'permission' => 'menu.companies.map'],
            ['path' => '/admin/tax-management', 'permission' => ['menu.admin.org-units', 'menu.admin.tax-units']],
            ['path' => '/admin/tax-units', 'permission' => 'menu.admin.tax-units'],
            ['path' => '/companies/identity-history', 'permission' => 'menu.reports.identity-history'],
            ['path' => '/admin/statuses', 'permission' => 'menu.companies.statuses'],
            ['path' => '/admin/menu-config', 'permission' => '__root__'],
        ];

        $walk = function (array $nodes) use (&$walk, &$map): void {
            foreach ($nodes as $node) {
                if (!empty($node['path']) && !empty($node['permission_key'])) {
                    $permission = $node['permission_keys'] ?? $node['permission_key'];
                    $pathname = explode('?', $node['path'])[0];
                    $map[] = ['path' => $pathname, 'permission' => $permission];
                }
                if (!empty($node['children'])) {
                    $walk($node['children']);
                }
            }
        };

        $walk(self::tree());

        return $map;
    }
}
