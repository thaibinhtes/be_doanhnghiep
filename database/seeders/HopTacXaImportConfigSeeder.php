<?php

namespace Database\Seeders;

use App\Models\HopTacXaImportConfig;
use App\Support\HopTacXaImportColumnMap;
use Illuminate\Database\Seeder;

class HopTacXaImportConfigSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = HopTacXaImportColumnMap::defaultExampleFormat();

        HopTacXaImportConfig::query()->updateOrCreate(
            ['code' => 'htx_example'],
            [
                'name' => 'Ánh xạ mẫu HTX',
                'description' => 'Template import Excel hợp tác xã (hàng 2, cột A–Q)',
                'start_row' => $defaults['start_row'],
                'column_map' => $defaults['column_map'],
                'value_extensions' => $defaults['value_extensions'],
                'is_active' => true,
                'sort_order' => 1,
            ],
        );
    }
}
