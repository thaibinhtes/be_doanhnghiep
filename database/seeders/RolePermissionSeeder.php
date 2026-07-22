<?php

namespace Database\Seeders;

use App\Models\DonVi;
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
        $rootDonViId = DonVi::ensureRoot()->id;

        $rootRole = Role::updateOrCreate(
            ['slug' => 'root'],
            [
                'name' => 'ROOT',
                'description' => 'Quản trị cấp cao nhất — toàn hệ thống',
                'level' => 100,
            ]
        );
        $rootRole->permissions()->sync($allPermissionIds);

        $adminRole = Role::updateOrCreate(
            ['slug' => 'admin'],
            [
                'name' => 'Quản trị đơn vị',
                'description' => 'Quản trị trong phạm vi đơn vị',
                'level' => 80,
            ]
        );
        $adminRole->permissions()->sync($allPermissionIds);

        $editorRole = Role::updateOrCreate(
            ['slug' => 'editor'],
            [
                'name' => 'Biên tập viên',
                'description' => 'Quản lý doanh nghiệp và thành viên',
                'level' => 50,
            ]
        );
        $editorRole->permissions()->sync(
            Permission::whereIn('key', PermissionRegistry::editorKeys())->pluck('id')
        );

        $viewerRole = Role::updateOrCreate(
            ['slug' => 'viewer'],
            [
                'name' => 'Người xem',
                'description' => 'Chỉ xem danh sách',
                'level' => 10,
            ]
        );
        $viewerRole->permissions()->sync(
            Permission::whereIn('key', PermissionRegistry::viewerKeys())->pluck('id')
        );

        User::updateOrCreate(
            ['email' => 'admin@htqldn.local'],
            [
                'name' => 'Administrator',
                'password' => 'password',
                'role_id' => $rootRole->id,
                'don_vi_id' => $rootDonViId,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'editor@htqldn.local'],
            [
                'name' => 'Editor',
                'password' => 'password',
                'role_id' => $editorRole->id,
                'don_vi_id' => $rootDonViId,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'viewer@htqldn.local'],
            [
                'name' => 'Viewer',
                'password' => 'password',
                'role_id' => $viewerRole->id,
                'don_vi_id' => $rootDonViId,
                'is_active' => true,
            ]
        );
    }
}
