<?php

namespace Database\Seeders;

use App\Models\DonVi;
use App\Models\User;
use Illuminate\Database\Seeder;

class DonViSeeder extends Seeder
{
    public function run(): void
    {
        $root = DonVi::updateOrCreate(
            ['parent_id' => null, 'ma' => 'ROOT'],
            [
                'cap' => 1,
                'ten' => 'Ban quản lý doanh nghiệp',
                'mo_ta' => 'Đơn vị gốc hệ thống',
                'thu_tu' => 0,
                'is_active' => true,
            ]
        );

        User::query()->whereNull('don_vi_id')->update(['don_vi_id' => $root->id]);
    }
}
