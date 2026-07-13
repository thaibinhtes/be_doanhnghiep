<?php

use App\Models\Permission;
use App\Support\NavMenuService;
use App\Support\PermissionRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (PermissionRegistry::all() as $permission) {
            Permission::updateOrCreate(
                ['key' => $permission['key']],
                $permission
            );
        }

        $keys = [
            'menu.admin.import-mapping-companies',
            'menu.admin.import-mapping-cooperatives',
            'feature.import-mapping.manage',
        ];

        $permissionIds = Permission::whereIn('key', $keys)->pluck('id');
        if ($permissionIds->isEmpty()) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('slug', ['root', 'admin'])
            ->pluck('id');

        $rows = [];
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $rows[] = [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('permission_role')->insertOrIgnore($rows);
        }

        app(NavMenuService::class)->syncFromRegistry();
    }

    public function down(): void
    {
        // Non-destructive.
    }
};
