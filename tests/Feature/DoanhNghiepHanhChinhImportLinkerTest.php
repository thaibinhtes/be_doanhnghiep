<?php

namespace Tests\Feature;

use App\Models\QuanHuyenCu;
use App\Models\TinhThanhCu;
use App\Support\DoanhNghiepHanhChinhImportLinker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DoanhNghiepHanhChinhImportLinkerTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('quan_huyen_cu');
        Schema::dropIfExists('tinh_thanh_cu');

        parent::tearDown();
    }

    public function test_resolves_legacy_province_and_constrains_district_match(): void
    {
        TinhThanhCu::query()->create([
            'code' => '01',
            'full_name' => 'Thành phố Hà Nội',
        ]);
        TinhThanhCu::query()->create([
            'code' => '02',
            'full_name' => 'Tỉnh Hà Giang',
        ]);

        QuanHuyenCu::query()->create([
            'code' => '001',
            'full_name' => 'Huyện Trùng Tên',
            'tinh_thanh_cu_code' => '01',
        ]);
        QuanHuyenCu::query()->create([
            'code' => '002',
            'full_name' => 'Huyện Trùng Tên',
            'tinh_thanh_cu_code' => '02',
        ]);

        $result = (new DoanhNghiepHanhChinhImportLinker)->resolve([
            'tinhThanhCu' => 'Thành phố Hà Nội',
            'quanHuyenCu' => 'Huyện Trùng Tên',
        ]);

        $this->assertSame('01', $result['snake']['tinh_thanh_cu_code']);
        $this->assertSame('001', $result['snake']['quan_huyen_cu_code']);
        $this->assertSame([], $result['notes']);
    }

    public function test_records_note_when_legacy_province_does_not_match(): void
    {
        $result = (new DoanhNghiepHanhChinhImportLinker)->resolve([
            'tinhThanhCu' => 'Tỉnh Không Tồn Tại',
        ]);

        $this->assertArrayNotHasKey('tinh_thanh_cu_code', $result['snake']);
        $this->assertSame(
            ['Tỉnh/Thành phố cũ chưa khớp danh mục: Tỉnh Không Tồn Tại'],
            $result['notes'],
        );
    }
}
