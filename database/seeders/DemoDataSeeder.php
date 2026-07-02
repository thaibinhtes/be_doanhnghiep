<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Init production-safe demo data: users/roles, trạng thái DN, doanh nghiệp demo.
 * Không dùng Faker — chạy được trong Docker production (--no-dev).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            DnTrangThaiSeeder::class,
            DemoDoanhNghiepSeeder::class,
        ]);
    }
}
