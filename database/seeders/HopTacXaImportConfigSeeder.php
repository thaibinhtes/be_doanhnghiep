<?php

namespace Database\Seeders;

use App\Models\HopTacXaImportConfig;
use App\Support\HopTacXaImportColumnMap;
use Illuminate\Database\Seeder;

class HopTacXaImportConfigSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = HopTacXaImportColumnMap::defaultStcExampleFormat();

        HopTacXaImportConfig::query()->updateOrCreate(
            ['code' => HopTacXaImportColumnMap::STC_EXAMPLE_CONFIG_CODE],
            [
                'name' => 'HTX STC example',
                'description' => 'Template import Excel hợp tác xã STC (dữ liệu từ hàng 10, cột A–J/M/P–R/U–V)',
                'start_row' => $defaults['start_row'],
                'column_map' => $defaults['column_map'],
                'value_extensions' => $defaults['value_extensions'],
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        HopTacXaImportConfig::query()
            ->where('code', 'htx_example')
            ->update(['is_active' => false]);
    }
}
