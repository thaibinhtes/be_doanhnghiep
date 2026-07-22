<?php

namespace Database\Seeders;

use App\Models\DonVi;
use App\Models\User;
use Illuminate\Database\Seeder;

class DonViSeeder extends Seeder
{
    public function run(): void
    {
        $root = DonVi::ensureRoot();

        User::query()->whereNull('don_vi_id')->update(['don_vi_id' => $root->id]);
    }
}
