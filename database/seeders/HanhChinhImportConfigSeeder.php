<?php

namespace Database\Seeders;

use App\Models\HanhChinhImportConfig;
use App\Support\HanhChinhImportColumnMap;
use Illuminate\Database\Seeder;

class HanhChinhImportConfigSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = HanhChinhImportColumnMap::defaultExampleFormat();
        $legacyOnly = HanhChinhImportColumnMap::legacyOnlyExampleFormat();
        $newOnly = HanhChinhImportColumnMap::newOnlyExampleFormat();

        HanhChinhImportConfig::query()->updateOrCreate(
            ['code' => HanhChinhImportColumnMap::EXAMPLE_CONFIG_CODE],
            [
                'name' => 'Mapping hành chính cũ → mới',
                'description' => 'STT + huyện + xã cũ + loại cũ + đơn vị mới + loại mới (không lưu tỉnh).',
                'start_row' => $defaults['start_row'],
                'column_map' => $defaults['column_map'],
                'value_extensions' => $defaults['value_extensions'],
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        HanhChinhImportConfig::query()->updateOrCreate(
            ['code' => HanhChinhImportColumnMap::LEGACY_ONLY_CONFIG_CODE],
            [
                'name' => 'Đơn vị hành chính cũ (3 cột)',
                'description' => 'Huyện/Thị xã + Đơn vị hành chính cũ + Loại (cũ).',
                'start_row' => $legacyOnly['start_row'],
                'column_map' => $legacyOnly['column_map'],
                'value_extensions' => $legacyOnly['value_extensions'],
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        HanhChinhImportConfig::query()->updateOrCreate(
            ['code' => HanhChinhImportColumnMap::NEW_ONLY_CONFIG_CODE],
            [
                'name' => 'Đơn vị hành chính mới (2 cột A/B)',
                'description' => 'File riêng 2 cột: Đơn vị hành chính mới + Loại (mới).',
                'start_row' => $newOnly['start_row'],
                'column_map' => $newOnly['column_map'],
                'value_extensions' => $newOnly['value_extensions'],
                'is_active' => true,
                'sort_order' => 3,
            ],
        );

        $newFromMapping = HanhChinhImportColumnMap::newFromMappingExampleFormat();

        HanhChinhImportConfig::query()->updateOrCreate(
            ['code' => HanhChinhImportColumnMap::NEW_FROM_MAPPING_CONFIG_CODE],
            [
                'name' => 'Đơn vị hành chính mới (file mapping F/G)',
                'description' => 'Lấy từ file mapping đầy đủ: cột F + G.',
                'start_row' => $newFromMapping['start_row'],
                'column_map' => $newFromMapping['column_map'],
                'value_extensions' => $newFromMapping['value_extensions'],
                'is_active' => true,
                'sort_order' => 4,
            ],
        );
    }
}
