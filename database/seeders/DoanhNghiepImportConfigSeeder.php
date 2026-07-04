<?php

namespace Database\Seeders;

use App\Models\DoanhNghiepImportConfig;
use App\Support\DoanhNghiepImportColumnMap;
use Illuminate\Database\Seeder;

class DoanhNghiepImportConfigSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = DoanhNghiepImportColumnMap::defaultSkhdtFormat();

        DoanhNghiepImportConfig::query()->updateOrCreate(
            ['code' => 'stc_example'],
            [
                'name' => 'Ánh xạ example STC',
                'description' => 'Template import Excel từ Sở KHĐT / đơn vị (hàng 13, cột B–AR)',
                'start_row' => $defaults['start_row'],
                'column_map' => $defaults['column_map'],
                'value_extensions' => $defaults['value_extensions'],
                'is_active' => true,
                'sort_order' => 1,
            ],
        );
    }
}
