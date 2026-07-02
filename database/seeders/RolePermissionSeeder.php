<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionRegistry;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionRegistry::all() as $permission) {
            Permission::updateOrCreate(
                ['key' => $permission['key']],
                $permission
            );
        }

        $allPermissionIds = Permission::pluck('id');

        $adminRole = Role::updateOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Quản trị viên', 'description' => 'Toàn quyền hệ thống']
        );
        $adminRole->permissions()->sync($allPermissionIds);

        $editorRole = Role::updateOrCreate(
            ['slug' => 'editor'],
            ['name' => 'Biên tập viên', 'description' => 'Quản lý doanh nghiệp và thành viên']
        );
        $editorRole->permissions()->sync(
            Permission::whereIn('key', PermissionRegistry::editorKeys())->pluck('id')
        );

        $viewerRole = Role::updateOrCreate(
            ['slug' => 'viewer'],
            ['name' => 'Người xem', 'description' => 'Chỉ xem danh sách']
        );
        $viewerRole->permissions()->sync(
            Permission::whereIn('key', PermissionRegistry::viewerKeys())->pluck('id')
        );

        User::updateOrCreate(
            ['email' => 'admin@htqldn.local'],
            [
                'name' => 'Administrator',
                'password' => 'password',
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'editor@htqldn.local'],
            [
                'name' => 'Editor',
                'password' => 'password',
                'role_id' => $editorRole->id,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'viewer@htqldn.local'],
            [
                'name' => 'Viewer',
                'password' => 'password',
                'role_id' => $viewerRole->id,
                'is_active' => true,
            ]
        );
    }
}
