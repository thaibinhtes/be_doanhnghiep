<?php

namespace Database\Seeders;

use App\Models\DoanhNghiepImportFormat;
use App\Models\User;
use App\Support\DoanhNghiepImportColumnMap;
use Illuminate\Database\Seeder;

class DoanhNghiepImportFormatSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = DoanhNghiepImportColumnMap::defaultSkhdtFormat();
        $name = 'Template SKHĐT / đơn vị';

        User::query()->orderBy('id')->each(function (User $user) use ($defaults, $name) {
            DoanhNghiepImportFormat::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => $name,
                ],
                [
                    'don_vi_id' => $user->don_vi_id,
                    'start_row' => $defaults['start_row'],
                    'column_map' => $defaults['column_map'],
                    'value_extensions' => $defaults['value_extensions'],
                ],
            );
        });
    }
}
