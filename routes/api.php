<?php

use App\Http\Controllers\Api\HanhChinhCuController;
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
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MemberCompanyController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TinhThanhController;
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

    Route::get('/tinh-thanh', [TinhThanhController::class, 'index']);
    Route::get('/tinh-thanh/{code}/xa-phuong', [TinhThanhController::class, 'xaPhuong']);

    Route::get('/hanh-chinh/cu/tinh-thanh', [HanhChinhCuController::class, 'indexProvinces']);
    Route::get('/hanh-chinh/cu/tinh-thanh/{provinceCode}/quan-huyen', [HanhChinhCuController::class, 'indexDistricts']);
    Route::get('/hanh-chinh/cu/quan-huyen/{districtCode}/xa-phuong', [HanhChinhCuController::class, 'indexWards']);
    Route::get('/hanh-chinh/mappings', [HanhChinhMappingController::class, 'index']);
    Route::get('/hanh-chinh/unmapped-doanh-nghiep', [HanhChinhMappingController::class, 'unmappedCompanies']);

    Route::middleware('permission:feature.cadastral.manage')->group(function () {
        Route::post('/hanh-chinh/cu/import', [HanhChinhCuController::class, 'bulkImport']);
        Route::post('/hanh-chinh/moi/import', [HanhChinhMoiController::class, 'bulkImport']);
        Route::post('/hanh-chinh/moi/import-dataset', [HanhChinhMoiController::class, 'importFromDataset']);
        Route::post('/hanh-chinh/mappings', [HanhChinhMappingController::class, 'store']);
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
    Route::get('/danh-muc-nganh/{danh_muc_nganh_nghe}', [DanhMucNganhNgheController::class, 'show']);
    Route::middleware('permission:feature.industry-categories.sync')->group(function () {
        Route::get('/danh-muc-nganh-export', [DanhMucNganhNgheController::class, 'exportCatalog']);
        Route::post('/danh-muc-nganh-import', [DanhMucNganhNgheController::class, 'importCatalog']);
    });
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
    Route::get('/doanh-nghiep/{doanhNghiep}/dinh-danh-lich-su', [DoanhNghiepController::class, 'dinhDanhLichSu']);
    Route::apiResource('doanh-nghiep', DoanhNghiepController::class);
    Route::patch('/doanh-nghiep/{doanhNghiep}/dinh-danh', [DoanhNghiepController::class, 'updateDinhDanh']);
    Route::get('/settings/company-import-docs', [SettingController::class, 'companyImportDocs']);
    Route::apiResource('members', MemberController::class);
    Route::apiResource('member-companies', MemberCompanyController::class);

    Route::post('/members/{member}/companies/attach', [MemberCompanyController::class, 'attachCompanies']);
    Route::post('/members/{member}/companies/detach', [MemberCompanyController::class, 'detachCompanies']);
    Route::post('/members/{member}/companies/sync', [MemberCompanyController::class, 'syncCompanies']);

    Route::post('/doanh-nghiep/{doanhNghiep}/members/attach', [MemberCompanyController::class, 'attachMembers']);
    Route::post('/doanh-nghiep/{doanhNghiep}/members/detach', [MemberCompanyController::class, 'detachMembers']);
    Route::post('/doanh-nghiep/{doanhNghiep}/members/sync', [MemberCompanyController::class, 'syncMembers']);
});
