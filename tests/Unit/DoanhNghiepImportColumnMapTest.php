<?php

namespace Tests\Unit;

use App\Support\DoanhNghiepImportColumnMap;
use PHPUnit\Framework\TestCase;

class DoanhNghiepImportColumnMapTest extends TestCase
{
    public function test_resolve_end_column_uses_index_not_string_compare(): void
    {
        $map = [
            'maSoDoanhNghiep' => ['B'],
            'tenDoanhNghiep' => ['C'],
            'loaiDN' => ['AA'],
            'trangThai' => ['Z'],
        ];

        $this->assertSame('AA', DoanhNghiepImportColumnMap::resolveEndColumn($map));
    }

    public function test_parse_row_reads_out_of_order_and_beyond_z(): void
    {
        $row = array_fill(0, 27, null);
        $row[1] = '0123456789'; // B
        $row[2] = 'Cong ty ABC'; // C
        $row[3] = 'Dia chi moi'; // D
        $row[8] = 'Tinh cu'; // I
        $row[25] = 'Dang HD'; // Z
        $row[26] = 'DN tu nhan'; // AA

        $map = [
            'loaiDN' => ['AA'],
            'tinhThanhCu' => ['I'],
            'diaChiMoi' => ['D'],
            'maSoDoanhNghiep' => ['B'],
            'tenDoanhNghiep' => ['C'],
            'trangThai' => ['Z'],
        ];

        $parsed = DoanhNghiepImportColumnMap::parseRow($row, $map);

        $this->assertSame('0123456789', $parsed['maSoDoanhNghiep']);
        $this->assertSame('Cong ty ABC', $parsed['tenDoanhNghiep']);
        $this->assertSame('Dia chi moi', $parsed['diaChiMoi']);
        $this->assertSame('Tinh cu', $parsed['tinhThanhCu']);
        $this->assertSame('Dang HD', $parsed['trangThai']);
        $this->assertSame('DN tu nhan', $parsed['loaiDN']);
    }

    public function test_read_columns_prefers_leftmost_when_map_order_reversed(): void
    {
        $row = array_fill(0, 10, null);
        $row[3] = 'Ten chinh'; // D
        $row[6] = 'Ten phu'; // G

        $parsed = DoanhNghiepImportColumnMap::parseRow($row, [
            'tenDoanhNghiep' => ['G', 'F', 'E', 'D'],
        ]);

        $this->assertSame('Ten chinh', $parsed['tenDoanhNghiep']);
    }
}
