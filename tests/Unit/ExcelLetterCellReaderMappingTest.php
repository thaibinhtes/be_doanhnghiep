<?php

namespace Tests\Unit;

use App\Support\DoanhNghiepImportColumnMap;
use Maatwebsite\Excel\Row as ExcelRow;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Row as SpreadsheetRow;
use PHPUnit\Framework\TestCase;

class ExcelLetterCellReaderMappingTest extends TestCase
{
    public function test_unordered_map_reads_exact_excel_letters(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('H3', 'HUYEN_FROM_H');
        $sheet->setCellValue('L3', 'STATUS_FROM_L');
        $sheet->setCellValue('B3', '0123456789');
        $sheet->setCellValue('C3', 'Cong ty Test');

        $row = new ExcelRow(new SpreadsheetRow($sheet, 3));

        $parsed = DoanhNghiepImportColumnMap::parseExcelRow($row, [
            'trangThai' => ['L'],
            'quanHuyenCu' => ['H'],
            'tenDoanhNghiep' => ['C'],
            'maSoDoanhNghiep' => ['B'],
        ]);

        $this->assertSame('HUYEN_FROM_H', $parsed['quanHuyenCu']);
        $this->assertSame('STATUS_FROM_L', $parsed['trangThai']);
        $this->assertSame('0123456789', $parsed['maSoDoanhNghiep']);
        $this->assertSame('Cong ty Test', $parsed['tenDoanhNghiep']);
    }

    public function test_merged_cell_reads_master_value_for_mapped_letter(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->mergeCells('H3:J3');
        $sheet->setCellValue('H3', 'HUYEN_MERGED');
        $sheet->setCellValue('L3', 'SHOULD_NOT_USE');

        $row = new ExcelRow(new SpreadsheetRow($sheet, 3));
        $parsed = DoanhNghiepImportColumnMap::parseExcelRow($row, [
            'quanHuyenCu' => ['I'], // nằm trong merge H:J
            'trangThai' => ['L'],
        ]);

        $this->assertSame('HUYEN_MERGED', $parsed['quanHuyenCu']);
        $this->assertSame('SHOULD_NOT_USE', $parsed['trangThai']);
    }
}
