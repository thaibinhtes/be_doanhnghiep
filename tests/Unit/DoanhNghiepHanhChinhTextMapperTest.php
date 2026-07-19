<?php

namespace Tests\Unit;

use App\Support\DoanhNghiepHanhChinhTextMapper;
use Tests\TestCase;

class DoanhNghiepHanhChinhTextMapperTest extends TestCase
{
    public function test_maps_admin_fields_as_text_and_clears_codes(): void
    {
        $result = (new DoanhNghiepHanhChinhTextMapper)->map([
            'tinhThanhCu' => 'Tỉnh A',
            'quanHuyenCu' => 'Huyện B',
            'quanHuyenMoi' => 'Huyện C',
            'phuongXaCu' => 'Xã D',
            'phuongXaMoi' => 'Xã E',
            'diaChiCu' => 'Địa chỉ cũ',
            'diaChiMoi' => 'Địa chỉ mới',
        ]);

        $this->assertSame('Tỉnh A', $result['tinh_thanh_cu']);
        $this->assertSame('Huyện B', $result['quan_huyen_cu']);
        $this->assertSame('Huyện C', $result['quan_huyen_moi']);
        $this->assertSame('Xã D', $result['xa_phuong_cu']);
        $this->assertSame('Xã E', $result['xa_phuong_moi']);
        $this->assertSame('Địa chỉ cũ', $result['dia_chi_cu']);
        $this->assertSame('Địa chỉ mới', $result['dia_chi_moi']);
        $this->assertSame('Địa chỉ mới', $result['dia_chi']);
        $this->assertSame('Huyện C', $result['quan_huyen']);
        $this->assertSame('Xã E', $result['phuong_xa']);
        $this->assertNull($result['tinh_thanh_cu_code']);
        $this->assertNull($result['quan_huyen_cu_code']);
        $this->assertNull($result['xa_phuong_cu_code']);
        $this->assertNull($result['tinh_thanh_code']);
        $this->assertNull($result['xa_phuong_code']);
    }

    public function test_prefers_cu_for_legacy_display_when_moi_empty(): void
    {
        $result = (new DoanhNghiepHanhChinhTextMapper)->map([
            'quanHuyenCu' => 'Huyện cũ',
            'phuongXaCu' => 'Xã cũ',
            'diaChiCu' => 'Địa chỉ cũ',
        ]);

        $this->assertSame('Huyện cũ', $result['quan_huyen']);
        $this->assertSame('Xã cũ', $result['phuong_xa']);
        $this->assertSame('Địa chỉ cũ', $result['dia_chi']);
    }
}
