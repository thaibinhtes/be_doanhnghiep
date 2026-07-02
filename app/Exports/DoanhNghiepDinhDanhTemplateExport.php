<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DoanhNghiepDinhDanhTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    public function array(): array
    {
        return [
            ['1600000001', 'Công ty TNHH Demo An Giang', 1],
            ['1600000002', 'Công ty Cổ phần Demo Châu Đốc', 0],
        ];
    }

    public function headings(): array
    {
        return [
            'mã số doanh nghiệp',
            'tên doanh nghiệp',
            'định danh (1/0)',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
