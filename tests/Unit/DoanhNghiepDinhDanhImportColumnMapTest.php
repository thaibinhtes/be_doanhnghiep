<?php

namespace Tests\Unit;

use App\Support\DoanhNghiepDinhDanhImportColumnMap;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class DoanhNghiepDinhDanhImportColumnMapTest extends TestCase
{
    public function test_default_map_includes_ngay_dinh_danh(): void
    {
        $map = DoanhNghiepDinhDanhImportColumnMap::DEFAULT_COLUMN_MAP;

        $this->assertSame(['C'], $map['ngayDinhDanh']);
        $this->assertArrayHasKey('ngayDinhDanh', DoanhNghiepDinhDanhImportColumnMap::COLUMN_LABELS);
    }

    public function test_empty_value_uses_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-21 10:30:00'));

        $date = DoanhNghiepDinhDanhImportColumnMap::resolveIdentityDate(null);

        $this->assertSame('2026-07-21', $date->toDateString());

        Carbon::setTestNow();
    }

    public function test_empty_value_prefers_fallback_then_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-21'));

        $date = DoanhNghiepDinhDanhImportColumnMap::resolveIdentityDate('', '2026-01-15');

        $this->assertSame('2026-01-15', $date->toDateString());

        Carbon::setTestNow();
    }

    public function test_parses_excel_date_formats(): void
    {
        $this->assertSame(
            '2026-03-05',
            DoanhNghiepDinhDanhImportColumnMap::resolveIdentityDate('05/03/2026')->toDateString(),
        );
        $this->assertSame(
            '2026-03-05',
            DoanhNghiepDinhDanhImportColumnMap::resolveIdentityDate('2026-03-05')->toDateString(),
        );
    }
}
