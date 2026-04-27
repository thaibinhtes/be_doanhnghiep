<?php

use App\Http\Controllers\Api\DoanhNghiepController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MemberCompanyController;
use App\Http\Controllers\Api\MemberController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'check']);

Route::apiResource('doanh-nghiep', DoanhNghiepController::class);
Route::apiResource('members', MemberController::class);
Route::apiResource('member-companies', MemberCompanyController::class);

// Member <> Company pivot management (bulk operations)
Route::post('/members/{member}/companies/attach', [MemberCompanyController::class, 'attachCompanies']);
Route::post('/members/{member}/companies/detach', [MemberCompanyController::class, 'detachCompanies']);
Route::post('/members/{member}/companies/sync', [MemberCompanyController::class, 'syncCompanies']);

Route::post('/doanh-nghiep/{doanhNghiep}/members/attach', [MemberCompanyController::class, 'attachMembers']);
Route::post('/doanh-nghiep/{doanhNghiep}/members/detach', [MemberCompanyController::class, 'detachMembers']);
Route::post('/doanh-nghiep/{doanhNghiep}/members/sync', [MemberCompanyController::class, 'syncMembers']);
