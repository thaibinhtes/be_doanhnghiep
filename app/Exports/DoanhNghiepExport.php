<?php

namespace App\Exports;

use App\Models\DoanhNghiep;
use App\Support\DoanhNghiepExcelColumns;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DoanhNghiepExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithColumnFormatting
{
    private int $sequence = 0;

    public function __construct(
        private readonly Builder $query,
    ) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return DoanhNghiepExcelColumns::headings();
    }

    /**
     * @param  DoanhNghiep  $doanhNghiep
     */
    public function map($doanhNghiep): array
    {
        $this->sequence++;

        return DoanhNghiepExcelColumns::exportValues($doanhNghiep, $this->sequence);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * Keep numeric-looking business fields as text in Excel (avoids re-import type errors).
     */
    public function columnFormats(): array
    {
        $formats = [];
        foreach (['maSoDoanhNghiep', 'vonDieuLe', 'dienThoai'] as $key) {
            $letter = DoanhNghiepExcelColumns::columnLetterForKey($key);
            if ($letter !== null) {
                $formats[$letter] = NumberFormat::FORMAT_TEXT;
            }
        }

        return $formats;
    }
}
