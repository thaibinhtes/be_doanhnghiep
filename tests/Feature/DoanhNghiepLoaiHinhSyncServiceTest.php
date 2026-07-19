<?php

namespace Tests\Feature;

use App\Models\DnLoaiHinh;
use App\Models\DoanhNghiep;
use App\Support\DoanhNghiepLoaiHinhSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DoanhNghiepLoaiHinhSyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('dn_loai_hinhs', function (Blueprint $table) {
            $table->id();
            $table->string('ma', 50)->unique();
            $table->string('ten');
            $table->unsignedSmallInteger('thu_tu')->default(0);
            $table->boolean('mac_dinh')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('mo_ta')->nullable();
            $table->timestamps();
        });
        Schema::create('doanh_nghieps', function (Blueprint $table) {
            $table->id();
            $table->string('ten_doanh_nghiep');
            $table->string('loai_hinh_dn')->nullable();
            $table->unsignedBigInteger('dn_loai_hinh_id')->nullable();
            $table->unsignedBigInteger('don_vi_id')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('doanh_nghieps');
        Schema::dropIfExists('dn_loai_hinhs');

        parent::tearDown();
    }

    public function test_creates_catalog_links_company_and_keeps_source_text(): void
    {
        $company = DoanhNghiep::query()->create([
            'ten_doanh_nghiep' => 'Công ty thử nghiệm',
            'loai_hinh_dn' => '  Loại hình Đặc biệt  ',
        ]);

        $result = app(DoanhNghiepLoaiHinhSyncService::class)->sync();
        $company->refresh();

        $this->assertSame(1, $result['createdTypes']);
        $this->assertSame(1, $result['updatedCompanies']);
        $this->assertSame('  Loại hình Đặc biệt  ', $company->loai_hinh_dn);
        $this->assertNotNull($company->dn_loai_hinh_id);
        $this->assertSame(
            'Loại hình Đặc biệt',
            DnLoaiHinh::query()->findOrFail($company->dn_loai_hinh_id)->ten,
        );

        $second = app(DoanhNghiepLoaiHinhSyncService::class)->sync();
        $this->assertSame(0, $second['createdTypes']);
        $this->assertSame(0, $second['updatedCompanies']);
    }

    public function test_dry_run_does_not_write_catalog_or_company(): void
    {
        $company = DoanhNghiep::query()->create([
            'ten_doanh_nghiep' => 'Công ty dry run',
            'loai_hinh_dn' => 'Loại chỉ xem trước',
        ]);

        $result = app(DoanhNghiepLoaiHinhSyncService::class)->sync(true);

        $this->assertSame(1, $result['createdTypes']);
        $this->assertSame(1, $result['updatedCompanies']);
        $this->assertDatabaseMissing('dn_loai_hinhs', ['ten' => 'Loại chỉ xem trước']);
        $this->assertNull($company->fresh()->dn_loai_hinh_id);
    }
}
