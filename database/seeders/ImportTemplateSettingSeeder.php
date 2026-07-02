<?php

namespace Database\Seeders;

use App\Exports\DoanhNghiepDinhDanhTemplateExport;
use App\Exports\DoanhNghiepTemplateExport;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class ImportTemplateSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companyTemplateRelativePath = 'examples/mau-import-doanh-nghiep.xlsx';
        $identityTemplateRelativePath = 'examples/mau-import-dinh-danh-doanh-nghiep.xlsx';

        Excel::store(new DoanhNghiepTemplateExport(), $companyTemplateRelativePath, 'public');
        Excel::store(new DoanhNghiepDinhDanhTemplateExport(), $identityTemplateRelativePath, 'public');

        $settings = [
            [
                'key' => 'companies.import_template_url',
                'value' => '/storage/' . $companyTemplateRelativePath,
                'description' => 'Link file mẫu import danh sách doanh nghiệp',
            ],
            [
                'key' => 'companies.identity_import_template_url',
                'value' => '/storage/' . $identityTemplateRelativePath,
                'description' => 'Link file mẫu import định danh doanh nghiệp',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::query()->updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'description' => $setting['description'],
                ]
            );
        }
    }
}
