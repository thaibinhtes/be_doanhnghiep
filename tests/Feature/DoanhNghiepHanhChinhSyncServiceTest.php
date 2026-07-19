<?php

namespace Tests\Feature;

use App\Models\DoanhNghiep;
use App\Support\DoanhNghiepHanhChinhSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DoanhNghiepHanhChinhSyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tinh_thanh_cu', function (Blueprint $table) {
            $table->string('code', 32)->primary();
            $table->string('full_name');
            $table->timestamps();
        });
        Schema::create('quan_huyen_cu', function (Blueprint $table) {
            $table->string('code', 32)->primary();
            $table->string('full_name');
            $table->string('tinh_thanh_cu_code', 32)->nullable();
            $table->timestamps();
        });
        Schema::create('xa_phuong_cu', function (Blueprint $table) {
            $table->string('code', 32)->primary();
            $table->string('full_name');
            $table->string('unit_type', 32)->nullable();
            $table->string('quan_huyen_cu_code', 32);
            $table->timestamps();
        });
        Schema::create('tinh_thanh', function (Blueprint $table) {
            $table->string('code', 20)->primary();
            $table->string('full_name');
            $table->timestamps();
        });
        Schema::create('xa_phuong', function (Blueprint $table) {
            $table->string('code', 20)->primary();
            $table->string('full_name');
            $table->string('unit_type', 32)->nullable();
            $table->string('tinh_thanh_code', 20);
            $table->timestamps();
        });
        Schema::create('doanh_nghieps', function (Blueprint $table) {
            $table->id();
            $table->string('ten_doanh_nghiep');
            $table->string('tinh_thanh_cu')->nullable();
            $table->string('quan_huyen_cu')->nullable();
            $table->string('xa_phuong_cu')->nullable();
            $table->string('quan_huyen_moi')->nullable();
            $table->string('xa_phuong_moi')->nullable();
            $table->text('dia_chi_cu')->nullable();
            $table->text('dia_chi_moi')->nullable();
            $table->string('tinh_thanh_cu_code', 32)->nullable();
            $table->string('quan_huyen_cu_code', 32)->nullable();
            $table->string('xa_phuong_cu_code', 32)->nullable();
            $table->string('tinh_thanh_code', 20)->nullable();
            $table->string('xa_phuong_code', 20)->nullable();
            $table->unsignedBigInteger('don_vi_id')->nullable();
            $table->timestamp('hanh_chinh_synced_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        foreach (['doanh_nghieps', 'xa_phuong', 'tinh_thanh', 'xa_phuong_cu', 'quan_huyen_cu', 'tinh_thanh_cu'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_creates_catalog_tree_links_codes_and_keeps_all_text(): void
    {
        $source = [
            'ten_doanh_nghiep' => 'Công ty địa bàn',
            'tinh_thanh_cu' => 'Tỉnh Cũ A',
            'quan_huyen_cu' => 'Huyện Cũ B',
            'xa_phuong_cu' => 'Xã Cũ C',
            'quan_huyen_moi' => 'Tỉnh Mới D',
            'xa_phuong_moi' => 'Xã Mới E',
            'dia_chi_cu' => 'Địa chỉ cũ giữ nguyên',
            'dia_chi_moi' => 'Địa chỉ mới giữ nguyên',
        ];
        $company = DoanhNghiep::query()->create($source);

        $result = app(DoanhNghiepHanhChinhSyncService::class)->sync();
        $company->refresh();

        $this->assertSame(1, $result['createdLegacyProvinces']);
        $this->assertSame(1, $result['createdLegacyDistricts']);
        $this->assertSame(1, $result['createdLegacyWards']);
        $this->assertSame(1, $result['createdNewProvinces']);
        $this->assertSame(1, $result['createdNewWards']);
        $this->assertSame(1, $result['updatedCompanies']);
        $this->assertNotNull($company->tinh_thanh_cu_code);
        $this->assertNotNull($company->quan_huyen_cu_code);
        $this->assertNotNull($company->xa_phuong_cu_code);
        $this->assertNotNull($company->tinh_thanh_code);
        $this->assertNotNull($company->xa_phuong_code);

        foreach ($source as $column => $value) {
            $this->assertSame($value, $company->{$column});
        }

        $second = app(DoanhNghiepHanhChinhSyncService::class)->sync();
        $this->assertSame(0, $second['createdLegacyProvinces']);
        $this->assertSame(0, $second['createdLegacyDistricts']);
        $this->assertSame(0, $second['createdLegacyWards']);
        $this->assertSame(0, $second['createdNewProvinces']);
        $this->assertSame(0, $second['createdNewWards']);
        $this->assertSame(0, $second['updatedCompanies']);
    }

    public function test_dry_run_does_not_create_catalog_or_update_codes(): void
    {
        $company = DoanhNghiep::query()->create([
            'ten_doanh_nghiep' => 'Công ty dry run địa bàn',
            'tinh_thanh_cu' => 'Tỉnh Dry',
            'quan_huyen_cu' => 'Huyện Dry',
            'xa_phuong_cu' => 'Xã Dry',
        ]);

        $result = app(DoanhNghiepHanhChinhSyncService::class)->sync(true);

        $this->assertSame(1, $result['createdLegacyProvinces']);
        $this->assertDatabaseMissing('tinh_thanh_cu', ['full_name' => 'Tỉnh Dry']);
        $this->assertNull($company->fresh()->tinh_thanh_cu_code);
    }
}
