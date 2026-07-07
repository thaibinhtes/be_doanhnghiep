<?php

use App\Http\Controllers\Api\HanhChinhCuController;
use App\Http\Controllers\Api\HanhChinhImportConfigController;
use App\Http\Controllers\Api\HanhChinhImportFormatController;
use App\Http\Controllers\Api\HanhChinhMappingController;
use App\Http\Controllers\Api\HanhChinhMoiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DanhMucNganhNgheController;
use App\Http\Controllers\Api\DonViController;
use App\Http\Controllers\Api\DnLoaiHinhController;
use App\Http\Controllers\Api\DnTrangThaiController;
use App\Http\Controllers\Api\DoanhNghiepController;
use App\Http\Controllers\Api\DoanhNghiepImportJobController;
use App\Http\Controllers\Api\DoanhNghiepImportConfigController;
use App\Http\Controllers\Api\DoanhNghiepImportFormatController;
use App\Http\Controllers\Api\HopTacXaController;
use App\Http\Controllers\Api\HopTacXaImportJobController;
use App\Http\Controllers\Api\HopTacXaImportConfigController;
use App\Http\Controllers\Api\HopTacXaImportFormatController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MemberCompanyController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TinhThanhController;
use App\Http\Controllers\Api\TaxManagementController;
use App\Http\Controllers\Api\TaxUnitController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'check']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::middleware('permission:feature.roles.manage')->group(function () {
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::get('/roles', [RoleController::class, 'index']);
        Route::get('/roles/{role}', [RoleController::class, 'show']);
        Route::put('/roles/{role}/permissions', [RoleController::class, 'updatePermissions']);
    });

    Route::middleware('permission:feature.users.manage')->group(function () {
        Route::get('/users/assignable-roles', [UserController::class, 'assignableRoles']);
        Route::apiResource('users', UserController::class);
    });

    Route::get('/don-vi', [DonViController::class, 'index']);
    Route::get('/don-vi/{don_vi}', [DonViController::class, 'show']);
    Route::middleware('permission:feature.org-units.manage')->group(function () {
        Route::post('/don-vi', [DonViController::class, 'store']);
        Route::put('/don-vi/{don_vi}', [DonViController::class, 'update']);
        Route::patch('/don-vi/{don_vi}', [DonViController::class, 'update']);
        Route::delete('/don-vi/{don_vi}', [DonViController::class, 'destroy']);
    });

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/dinh-danh-theo-ngay', [DashboardController::class, 'dinhDanhTheoNgay']);

    Route::get('/tinh-thanh', [TinhThanhController::class, 'index']);
    Route::get('/tinh-thanh/{code}/xa-phuong', [TinhThanhController::class, 'xaPhuong']);

    Route::get('/hanh-chinh/cu/tinh-thanh', [HanhChinhCuController::class, 'indexProvinces']);
    Route::get('/hanh-chinh/cu/quan-huyen', [HanhChinhCuController::class, 'indexDistricts']);
    Route::get('/hanh-chinh/cu/don-vi', [HanhChinhCuController::class, 'indexLegacyUnits']);
    Route::get('/hanh-chinh/cu/tinh-thanh/{provinceCode}/quan-huyen', [HanhChinhCuController::class, 'indexDistrictsByProvince']);
    Route::get('/hanh-chinh/cu/quan-huyen/{districtCode}/xa-phuong', [HanhChinhCuController::class, 'indexWards']);
    Route::get('/hanh-chinh/cu/import-column-map', [HanhChinhCuController::class, 'importColumnMap']);
    Route::get('/hanh-chinh/cu/import-configs', [HanhChinhImportConfigController::class, 'index']);
    Route::get('/hanh-chinh/cu/import-formats', [HanhChinhImportFormatController::class, 'index']);
    Route::get('/hanh-chinh/moi/don-vi', [HanhChinhMoiController::class, 'indexNewUnits']);
    Route::get('/hanh-chinh/moi/import-column-map', [HanhChinhMoiController::class, 'importColumnMap']);
    Route::get('/hanh-chinh/mappings', [HanhChinhMappingController::class, 'index']);
    Route::get('/hanh-chinh/mappings/groups', [HanhChinhMappingController::class, 'indexGroups']);
    Route::get('/hanh-chinh/unmapped-doanh-nghiep', [HanhChinhMappingController::class, 'unmappedCompanies']);

    Route::middleware('permission:feature.cadastral.manage')->group(function () {
        Route::post('/hanh-chinh/cu/import', [HanhChinhCuController::class, 'bulkImport']);
        Route::post('/hanh-chinh/cu/import-excel', [HanhChinhCuController::class, 'importExcel']);
        Route::post('/hanh-chinh/cu/import-formats', [HanhChinhImportFormatController::class, 'store']);
        Route::put('/hanh-chinh/cu/import-formats/{importFormat}', [HanhChinhImportFormatController::class, 'update']);
        Route::delete('/hanh-chinh/cu/import-formats/{importFormat}', [HanhChinhImportFormatController::class, 'destroy']);
        Route::post('/hanh-chinh/moi/import', [HanhChinhMoiController::class, 'bulkImport']);
        Route::post('/hanh-chinh/moi/import-excel', [HanhChinhMoiController::class, 'importExcel']);
        Route::post('/hanh-chinh/moi/import-dataset', [HanhChinhMoiController::class, 'importFromDataset']);
        Route::get('/hanh-chinh/moi/clear/preview', [HanhChinhMoiController::class, 'clearPreview']);
        Route::delete('/hanh-chinh/moi/clear', [HanhChinhMoiController::class, 'clear']);
        Route::post('/hanh-chinh/mappings', [HanhChinhMappingController::class, 'store']);
        Route::post('/hanh-chinh/mappings/link', [HanhChinhMappingController::class, 'link']);
        Route::post('/hanh-chinh/mappings/import-excel', [HanhChinhMappingController::class, 'importExcel']);
        Route::post('/hanh-chinh/mappings/import', [HanhChinhMappingController::class, 'bulkImport']);
        Route::put('/hanh-chinh/mappings/{hanhChinhMapping}', [HanhChinhMappingController::class, 'update']);
        Route::patch('/hanh-chinh/mappings/{hanhChinhMapping}', [HanhChinhMappingController::class, 'update']);
        Route::delete('/hanh-chinh/mappings/{hanhChinhMapping}', [HanhChinhMappingController::class, 'destroy']);
        Route::post('/hanh-chinh/sync-doanh-nghiep', [HanhChinhMappingController::class, 'syncCompanies']);
    });

    Route::get('/reports/tong-hop', [ReportController::class, 'tongHop']);
    Route::get('/reports/tong-hop/export', [ReportController::class, 'exportTongHop']);
    Route::get('/reports/tien-do-dinh-danh', [ReportController::class, 'tienDoDinhDanh']);
    Route::get('/reports/tien-do-dinh-danh/export', [ReportController::class, 'exportTienDoDinhDanh']);

    Route::get('/dn-trang-thai', [DnTrangThaiController::class, 'index']);
    Route::get('/dn-trang-thai/{dn_trang_thai}', [DnTrangThaiController::class, 'show']);
    Route::middleware('permission:feature.statuses.manage')->group(function () {
        Route::post('/dn-trang-thai', [DnTrangThaiController::class, 'store']);
        Route::put('/dn-trang-thai/{dn_trang_thai}', [DnTrangThaiController::class, 'update']);
        Route::patch('/dn-trang-thai/{dn_trang_thai}', [DnTrangThaiController::class, 'update']);
        Route::delete('/dn-trang-thai/{dn_trang_thai}', [DnTrangThaiController::class, 'destroy']);
    });

    Route::get('/dn-loai-hinh', [DnLoaiHinhController::class, 'index']);
    Route::get('/dn-loai-hinh/{dn_loai_hinh}', [DnLoaiHinhController::class, 'show']);
    Route::middleware('permission:feature.business-types.manage')->group(function () {
        Route::post('/dn-loai-hinh', [DnLoaiHinhController::class, 'store']);
        Route::put('/dn-loai-hinh/{dn_loai_hinh}', [DnLoaiHinhController::class, 'update']);
        Route::patch('/dn-loai-hinh/{dn_loai_hinh}', [DnLoaiHinhController::class, 'update']);
        Route::delete('/dn-loai-hinh/{dn_loai_hinh}', [DnLoaiHinhController::class, 'destroy']);
    });

    Route::get('/danh-muc-nganh', [DanhMucNganhNgheController::class, 'index']);
    Route::middleware('permission:feature.industry-categories.manage')->group(function () {
        Route::get('/danh-muc-nganh-export', [DanhMucNganhNgheController::class, 'exportCatalog']);
        Route::post('/danh-muc-nganh-import', [DanhMucNganhNgheController::class, 'importCatalog']);
    });
    Route::get('/danh-muc-nganh/{danh_muc_nganh_nghe}', [DanhMucNganhNgheController::class, 'show']);
    Route::middleware('permission:feature.industry-categories.manage')->group(function () {
        Route::post('/danh-muc-nganh', [DanhMucNganhNgheController::class, 'store']);
        Route::put('/danh-muc-nganh/{danh_muc_nganh_nghe}', [DanhMucNganhNgheController::class, 'update']);
        Route::patch('/danh-muc-nganh/{danh_muc_nganh_nghe}', [DanhMucNganhNgheController::class, 'update']);
        Route::delete('/danh-muc-nganh/{danh_muc_nganh_nghe}', [DanhMucNganhNgheController::class, 'destroy']);
    });

    Route::get('/doanh-nghiep/export', [DoanhNghiepController::class, 'export']);
    Route::get('/doanh-nghiep/export-template', [DoanhNghiepController::class, 'exportTemplate']);
    Route::get('/doanh-nghiep/export-template-dinh-danh', [DoanhNghiepController::class, 'exportIdentityTemplate']);
    Route::get('/doanh-nghiep/import-column-map', [DoanhNghiepController::class, 'importColumnMap']);
    Route::get('/doanh-nghiep/import-configs', [DoanhNghiepImportConfigController::class, 'index']);
    Route::get('/doanh-nghiep/import-formats', [DoanhNghiepImportFormatController::class, 'index']);
    Route::post('/doanh-nghiep/import-formats', [DoanhNghiepImportFormatController::class, 'store']);
    Route::put('/doanh-nghiep/import-formats/{importFormat}', [DoanhNghiepImportFormatController::class, 'update']);
    Route::delete('/doanh-nghiep/import-formats/{importFormat}', [DoanhNghiepImportFormatController::class, 'destroy']);
    Route::post('/doanh-nghiep/import', [DoanhNghiepController::class, 'import']);
    Route::get('/doanh-nghiep/import-jobs', [DoanhNghiepImportJobController::class, 'index']);
    Route::get('/doanh-nghiep/import-jobs/{importJob}', [DoanhNghiepImportJobController::class, 'show']);
    Route::get('/doanh-nghiep/import-jobs/{importJob}/rows', [DoanhNghiepImportJobController::class, 'rows']);
    Route::post('/doanh-nghiep/import-dinh-danh', [DoanhNghiepController::class, 'importDinhDanh']);
    Route::patch('/doanh-nghiep/dinh-danh/bulk', [DoanhNghiepController::class, 'bulkUpdateDinhDanh']);
    Route::delete('/doanh-nghiep/bulk', [DoanhNghiepController::class, 'bulkDestroy']);
    Route::get('/doanh-nghiep/clear-by-don-vi/preview', [DoanhNghiepController::class, 'clearByDonViPreview']);
    Route::delete('/doanh-nghiep/clear-by-don-vi', [DoanhNghiepController::class, 'clearByDonVi']);
    Route::get('/doanh-nghiep/{doanhNghiep}/dinh-danh-lich-su', [DoanhNghiepController::class, 'dinhDanhLichSu']);
    Route::apiResource('doanh-nghiep', DoanhNghiepController::class);
    Route::patch('/doanh-nghiep/{doanhNghiep}/dinh-danh', [DoanhNghiepController::class, 'updateDinhDanh']);
    Route::get('/settings/company-import-docs', [SettingController::class, 'companyImportDocs']);

    Route::get('/hop-tac-xa/export', [HopTacXaController::class, 'export']);
    Route::get('/hop-tac-xa/export-template', [HopTacXaController::class, 'exportTemplate']);
    Route::get('/hop-tac-xa/import-column-map', [HopTacXaController::class, 'importColumnMap']);
    Route::get('/hop-tac-xa/import-configs', [HopTacXaImportConfigController::class, 'index']);
    Route::get('/hop-tac-xa/import-formats', [HopTacXaImportFormatController::class, 'index']);
    Route::post('/hop-tac-xa/import-formats', [HopTacXaImportFormatController::class, 'store']);
    Route::put('/hop-tac-xa/import-formats/{importFormat}', [HopTacXaImportFormatController::class, 'update']);
    Route::delete('/hop-tac-xa/import-formats/{importFormat}', [HopTacXaImportFormatController::class, 'destroy']);
    Route::post('/hop-tac-xa/import', [HopTacXaController::class, 'import']);
    Route::get('/hop-tac-xa/import-jobs', [HopTacXaImportJobController::class, 'index']);
    Route::get('/hop-tac-xa/import-jobs/{importJob}', [HopTacXaImportJobController::class, 'show']);
    Route::get('/hop-tac-xa/import-jobs/{importJob}/rows', [HopTacXaImportJobController::class, 'rows']);
    Route::delete('/hop-tac-xa/bulk', [HopTacXaController::class, 'bulkDestroy']);
    Route::get('/hop-tac-xa/clear-by-don-vi/preview', [HopTacXaController::class, 'clearByDonViPreview']);
    Route::delete('/hop-tac-xa/clear-by-don-vi', [HopTacXaController::class, 'clearByDonVi']);
    Route::apiResource('hop-tac-xa', HopTacXaController::class);

    Route::get('/tax-units', [TaxUnitController::class, 'index']);
    Route::get('/tax-units/import-column-map', [TaxUnitController::class, 'importColumnMap']);
    Route::get('/tax-management/companies', [TaxManagementController::class, 'companyList']);
    Route::get('/tax-management/companies/import-column-map', [TaxManagementController::class, 'companyImportColumnMap']);
    Route::get('/tax-management/cooperatives', [TaxManagementController::class, 'cooperativeList']);
    Route::middleware('permission:feature.org-units.manage')->group(function () {
        Route::post('/tax-units', [TaxUnitController::class, 'store']);
        Route::post('/tax-units/import-excel', [TaxUnitController::class, 'importExcel']);
        Route::put('/tax-units/{taxUnit}', [TaxUnitController::class, 'update']);
        Route::patch('/tax-units/{taxUnit}', [TaxUnitController::class, 'update']);
        Route::delete('/tax-units/{taxUnit}', [TaxUnitController::class, 'destroy']);
        Route::post('/tax-management/companies', [TaxManagementController::class, 'upsertCompany']);
        Route::post('/tax-management/companies/import-excel', [TaxManagementController::class, 'importCompanyExcel']);
        Route::post('/tax-management/cooperatives', [TaxManagementController::class, 'upsertCooperative']);
    });

    Route::apiResource('members', MemberController::class);
    Route::apiResource('member-companies', MemberCompanyController::class);

    Route::post('/members/{member}/companies/attach', [MemberCompanyController::class, 'attachCompanies']);
    Route::post('/members/{member}/companies/detach', [MemberCompanyController::class, 'detachCompanies']);
    Route::post('/members/{member}/companies/sync', [MemberCompanyController::class, 'syncCompanies']);

    Route::post('/doanh-nghiep/{doanhNghiep}/members/attach', [MemberCompanyController::class, 'attachMembers']);
    Route::post('/doanh-nghiep/{doanhNghiep}/members/detach', [MemberCompanyController::class, 'detachMembers']);
    Route::post('/doanh-nghiep/{doanhNghiep}/members/sync', [MemberCompanyController::class, 'syncMembers']);
});
