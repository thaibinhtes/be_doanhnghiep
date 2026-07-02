<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DnTrangThaiController;
use App\Http\Controllers\Api\DoanhNghiepController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MemberCompanyController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoleController;
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

    Route::get('/doanh-nghiep/export', [DoanhNghiepController::class, 'export']);
    Route::get('/doanh-nghiep/export-template', [DoanhNghiepController::class, 'exportTemplate']);
    Route::post('/doanh-nghiep/import', [DoanhNghiepController::class, 'import']);
    Route::apiResource('doanh-nghiep', DoanhNghiepController::class);
    Route::patch('/doanh-nghiep/{doanhNghiep}/dinh-danh', [DoanhNghiepController::class, 'updateDinhDanh']);
    Route::apiResource('members', MemberController::class);
    Route::apiResource('member-companies', MemberCompanyController::class);

    Route::post('/members/{member}/companies/attach', [MemberCompanyController::class, 'attachCompanies']);
    Route::post('/members/{member}/companies/detach', [MemberCompanyController::class, 'detachCompanies']);
    Route::post('/members/{member}/companies/sync', [MemberCompanyController::class, 'syncCompanies']);

    Route::post('/doanh-nghiep/{doanhNghiep}/members/attach', [MemberCompanyController::class, 'attachMembers']);
    Route::post('/doanh-nghiep/{doanhNghiep}/members/detach', [MemberCompanyController::class, 'detachMembers']);
    Route::post('/doanh-nghiep/{doanhNghiep}/members/sync', [MemberCompanyController::class, 'syncMembers']);
});
