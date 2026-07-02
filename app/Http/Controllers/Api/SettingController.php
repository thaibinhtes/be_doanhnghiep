<?php

namespace App\Http\Controllers\Api;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class SettingController extends ApiController
{
    public function companyImportDocs(): JsonResponse
    {
        $defaults = [
            'companies.import_template_url' => '/storage/examples/mau-import-doanh-nghiep.xlsx',
            'companies.identity_import_template_url' => '/storage/examples/mau-import-dinh-danh-doanh-nghiep.xlsx',
        ];

        $rows = Setting::query()
            ->whereIn('key', array_keys($defaults))
            ->get(['key', 'value'])
            ->pluck('value', 'key')
            ->toArray();

        return $this->success([
            'companyImportTemplateUrl' => $rows['companies.import_template_url'] ?? $defaults['companies.import_template_url'],
            'companyIdentityImportTemplateUrl' => $rows['companies.identity_import_template_url'] ?? $defaults['companies.identity_import_template_url'],
        ]);
    }
}
