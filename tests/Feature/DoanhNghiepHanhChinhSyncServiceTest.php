<?php

namespace Tests\Feature;

use App\Models\DoanhNghiep;
use App\Models\HanhChinhPhuongXa;
use App\Models\HanhChinhQuanHuyen;
use App\Models\HanhChinhTinh;
use App\Support\DoanhNghiepHanhChinhSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DoanhNghiepHanhChinhSyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('hanh_chinh_tinh', function (Blueprint $table) {
            $table->id();
            $table->string('ten');
            $table->string('loai', 8);
            $table->string('ma', 32)->nullable();
            $table->timestamps();
        });
        Schema::create('hanh_chinh_quan_huyen', function (Blueprint $table) {
            $table->id();
            $table->string('ten');
            $table->string('loai', 8);
            $table->string('ma', 32)->nullable();
            $table->unsignedBigInteger('tinh_id')->nullable();
            $table->timestamps();
        });
        Schema::create('hanh_chinh_phuong_xa', function (Blueprint $table) {
            $table->id();
            $table->string('ten');
            $table->string('loai', 8);
            $table->string('ma', 32)->nullable();
            $table->unsignedBigInteger('quan_huyen_id')->nullable();
            $table->unsignedBigInteger('tinh_id')->nullable();
            $table->timestamps();
        });
        Schema::create('doanh_nghieps', function (Blueprint $table) {
            $table->id();
            $table->string('ten_doanh_nghiep');
            $table->string('tinh_thanh_cu')->nullable();
            $table->string('tinh_thanh_moi')->nullable();
            $table->string('quan_huyen_cu')->nullable();
            $table->string('xa_phuong_cu')->nullable();
            $table->string('quan_huyen_moi')->nullable();
            $table->string('xa_phuong_moi')->nullable();
            $table->text('dia_chi_cu')->nullable();
            $table->text('dia_chi_moi')->nullable();
            $table->unsignedBigInteger('tinh_thanh_cu_id')->nullable();
            $table->unsignedBigInteger('tinh_thanh_moi_id')->nullable();
            $table->unsignedBigInteger('quan_huyen_cu_id')->nullable();
            $table->unsignedBigInteger('xa_phuong_cu_id')->nullable();
            $table->unsignedBigInteger('quan_huyen_moi_id')->nullable();
            $table->unsignedBigInteger('xa_phuong_moi_id')->nullable();
            $table->unsignedBigInteger('don_vi_id')->nullable();
            $table->timestamp('hanh_chinh_synced_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        foreach (['doanh_nghieps', 'hanh_chinh_phuong_xa', 'hanh_chinh_quan_huyen', 'hanh_chinh_tinh'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_creates_unified_catalog_records_links_ids_and_keeps_all_text(): void
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

        $this->assertSame(1, $result['createdTinh']);
        $this->assertSame(2, $result['createdQuanHuyen']);
        $this->assertSame(2, $result['createdPhuongXa']);
        $this->assertSame(1, $result['updatedCompanies']);

        $tinhCu = HanhChinhTinh::query()->where('loai', 'cu')->where('ten', 'Tỉnh Cũ A')->firstOrFail();
        $quanCu = HanhChinhQuanHuyen::query()->where('loai', 'cu')->where('ten', 'Huyện Cũ B')->firstOrFail();
        $xaCu = HanhChinhPhuongXa::query()->where('loai', 'cu')->where('ten', 'Xã Cũ C')->firstOrFail();
        $quanMoi = HanhChinhQuanHuyen::query()->where('loai', 'moi')->where('ten', 'Tỉnh Mới D')->firstOrFail();
        $xaMoi = HanhChinhPhuongXa::query()->where('loai', 'moi')->where('ten', 'Xã Mới E')->firstOrFail();

        $this->assertSame($tinhCu->id, $company->tinh_thanh_cu_id);
        $this->assertSame($quanCu->id, $company->quan_huyen_cu_id);
        $this->assertSame($xaCu->id, $company->xa_phuong_cu_id);
        $this->assertSame($quanMoi->id, $company->quan_huyen_moi_id);
        $this->assertSame($xaMoi->id, $company->xa_phuong_moi_id);

        // Cây cha - con được gán đúng theo loại.
        $this->assertSame($tinhCu->id, $quanCu->tinh_id);
        $this->assertSame($quanCu->id, $xaCu->quan_huyen_id);
        $this->assertSame($quanMoi->id, $xaMoi->quan_huyen_id);

        foreach ($source as $column => $value) {
            $this->assertSame($value, $company->{$column});
        }

        $second = app(DoanhNghiepHanhChinhSyncService::class)->sync();
        $this->assertSame(0, $second['createdTinh']);
        $this->assertSame(0, $second['createdQuanHuyen']);
        $this->assertSame(0, $second['createdPhuongXa']);
        $this->assertSame(0, $second['updatedCompanies']);
        $this->assertSame(1, $second['alreadySynced']);
    }

    public function test_reuses_existing_catalog_records_and_skips_fields_already_linked(): void
    {
        $tinh = HanhChinhTinh::query()->create(['ten' => 'Tỉnh Cũ A', 'loai' => 'cu']);
        $quan = HanhChinhQuanHuyen::query()->create(['ten' => 'Huyện Cũ B', 'loai' => 'cu', 'tinh_id' => $tinh->id]);

        $company = DoanhNghiep::query()->create([
            'ten_doanh_nghiep' => 'Công ty đã sync một phần',
            'tinh_thanh_cu' => 'tỉnh cũ a',
            'quan_huyen_cu' => 'Huyện Cũ B',
            'xa_phuong_cu' => 'Xã Cũ C',
            'xa_phuong_cu_id' => 999, // đã sync trước đó → bỏ qua, không ghi đè
        ]);

        $result = app(DoanhNghiepHanhChinhSyncService::class)->sync();
        $company->refresh();

        $this->assertSame(0, $result['createdTinh']);
        $this->assertSame(0, $result['createdQuanHuyen']);
        $this->assertSame(0, $result['createdPhuongXa']);
        $this->assertSame($tinh->id, $company->tinh_thanh_cu_id);
        $this->assertSame($quan->id, $company->quan_huyen_cu_id);
        $this->assertSame(999, $company->xa_phuong_cu_id);
    }

    public function test_dry_run_does_not_create_catalog_or_update_ids(): void
    {
        $company = DoanhNghiep::query()->create([
            'ten_doanh_nghiep' => 'Công ty dry run địa bàn',
            'tinh_thanh_cu' => 'Tỉnh Dry',
            'quan_huyen_cu' => 'Huyện Dry',
            'xa_phuong_cu' => 'Xã Dry',
        ]);

        $result = app(DoanhNghiepHanhChinhSyncService::class)->sync(true);

        $this->assertSame(1, $result['createdTinh']);
        $this->assertSame(1, $result['createdQuanHuyen']);
        $this->assertSame(1, $result['createdPhuongXa']);
        $this->assertSame(1, $result['updatedCompanies']);
        $this->assertDatabaseMissing('hanh_chinh_tinh', ['ten' => 'Tỉnh Dry']);
        $this->assertNull($company->fresh()->tinh_thanh_cu_id);
    }

    public function test_syncs_each_selected_field_to_correct_catalog_type(): void
    {
        $company = DoanhNghiep::query()->create([
            'ten_doanh_nghiep' => 'Công ty sync từng field',
            'tinh_thanh_moi' => 'Tỉnh Mới',
            'quan_huyen_moi' => 'Huyện Mới',
            'xa_phuong_moi' => 'Xã Mới',
        ]);

        $service = app(DoanhNghiepHanhChinhSyncService::class);
        $province = $service->syncField('tinhThanhMoi');
        $district = $service->syncField('quanHuyenMoi');
        $ward = $service->syncField('phuongXaMoi');
        $company->refresh();

        $this->assertSame(1, $province['created']);
        $this->assertSame(1, $district['created']);
        $this->assertSame(1, $ward['created']);
        $this->assertSame('moi', $company->tinhThanhMoiRef()->firstOrFail()->loai);
        $this->assertSame('moi', $company->quanHuyenMoiRef()->firstOrFail()->loai);
        $this->assertSame('moi', $company->xaPhuongMoiRef()->firstOrFail()->loai);

        $again = $service->syncField('phuongXaMoi');
        $this->assertSame(1, $again['alreadyLinked']);
        $this->assertSame(0, $again['updated']);
    }
}
