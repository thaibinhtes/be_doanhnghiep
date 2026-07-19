<?php

namespace Tests\Unit;

use App\Support\DoanhNghiepFieldUpdateImportColumnMap;
use App\Support\DoanhNghiepFieldUpdateRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DoanhNghiepFieldUpdateImportColumnMapTest extends TestCase
{
    public function test_default_column_map_uses_msdn_and_phuong_xa_cu(): void
    {
        $map = DoanhNghiepFieldUpdateImportColumnMap::resolve(null);

        $this->assertSame(['A'], $map['maSoDoanhNghiep']);
        $this->assertSame(['B'], $map['phuongXaCu']);
    }

    public function test_assert_valid_rejects_missing_update_fields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cần ánh xạ ít nhất một field cần cập nhật.');

        DoanhNghiepFieldUpdateImportColumnMap::assertValid(
            ['maSoDoanhNghiep' => ['A']],
            'maSoDoanhNghiep',
        );
    }

    public function test_assert_valid_rejects_duplicate_columns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cột Excel A đang được dùng cho nhiều field.');

        DoanhNghiepFieldUpdateImportColumnMap::assertValid(
            [
                'maSoDoanhNghiep' => ['A'],
                'phuongXaCu' => ['A'],
            ],
            'maSoDoanhNghiep',
        );
    }

    public function test_lookup_and_update_whitelists(): void
    {
        $this->assertTrue(DoanhNghiepFieldUpdateRegistry::isLookupField('dienThoai'));
        $this->assertTrue(DoanhNghiepFieldUpdateRegistry::isUpdateField('phuongXaCu'));
        $this->assertTrue(DoanhNghiepFieldUpdateRegistry::isUpdateField('tinhThanhCu'));
        $this->assertFalse(DoanhNghiepFieldUpdateRegistry::isLookupField('phuongXaCu'));
        $this->assertFalse(DoanhNghiepFieldUpdateRegistry::isUpdateField('maSoDoanhNghiep'));
    }

    public function test_is_empty_row_skips_blank_payload(): void
    {
        $this->assertTrue(DoanhNghiepFieldUpdateImportColumnMap::isEmptyRow([]));
        $this->assertFalse(DoanhNghiepFieldUpdateImportColumnMap::isEmptyRow([
            'maSoDoanhNghiep' => '010203',
        ]));
    }
}
