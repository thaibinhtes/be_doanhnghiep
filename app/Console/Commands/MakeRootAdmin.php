<?php

namespace App\Console\Commands;

use App\Models\DonVi;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionRegistry;
use App\Support\RoleHierarchyHelper;
use Illuminate\Console\Command;

class MakeRootAdmin extends Command
{
    protected $signature = 'user:make-root
                            {email=admin@htqldn.local : Email tài khoản}
                            {--password=password : Mật khẩu khi tạo mới hoặc dùng --reset-password}
                            {--reset-password : Đặt lại mật khẩu cho tài khoản đã tồn tại}
                            {--name=Administrator : Tên hiển thị khi tạo mới}';

    protected $description = 'Gán vai trò ROOT cho user (mặc định admin@htqldn.local), đơn vị Sở Tài Chính';

    public function handle(): int
    {
        foreach (PermissionRegistry::all() as $permission) {
            Permission::updateOrCreate(
                ['key' => $permission['key']],
                $permission
            );
        }

        $rootRole = Role::updateOrCreate(
            ['slug' => RoleHierarchyHelper::SLUG_ROOT],
            [
                'name' => 'ROOT',
                'description' => 'Quản trị cấp cao nhất — toàn hệ thống',
                'level' => 100,
            ]
        );
        $rootRole->permissions()->sync(Permission::pluck('id'));

        $rootDonVi = DonVi::ensureRoot();

        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();
        $created = false;

        if (!$user) {
            $user = User::create([
                'name' => (string) $this->option('name'),
                'email' => $email,
                'password' => (string) $this->option('password'),
                'role_id' => $rootRole->id,
                'don_vi_id' => $rootDonVi->id,
                'is_active' => true,
            ]);
            $created = true;
        } else {
            $updates = [
                'role_id' => $rootRole->id,
                'don_vi_id' => $user->don_vi_id ?? $rootDonVi->id,
                'is_active' => true,
            ];

            if ($this->option('reset-password')) {
                $updates['password'] = (string) $this->option('password');
            }

            $user->update($updates);
        }

        $this->info(sprintf(
            '%s %s — vai trò ROOT, đơn vị %s (role_id=%d, don_vi_id=%d, permissions=%d)',
            $created ? 'Đã tạo' : 'Đã cập nhật',
            $email,
            DonVi::ROOT_TEN,
            $rootRole->id,
            $user->don_vi_id,
            $rootRole->permissions()->count(),
        ));

        if ($created || $this->option('reset-password')) {
            $this->line('Mật khẩu: '.$this->option('password'));
        }

        return self::SUCCESS;
    }
}
