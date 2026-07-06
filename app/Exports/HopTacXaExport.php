<?php

namespace App\Exports;

use App\Models\HopTacXa;
use App\Support\HopTacXaExcelColumns;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class HopTacXaExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithColumnFormatting
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
        return HopTacXaExcelColumns::headings();
    }

    /**
     * @param  HopTacXa  $hopTacXa
     */
    public function map($hopTacXa): array
    {
        $this->sequence++;

        return HopTacXaExcelColumns::exportValues($hopTacXa, $this->sequence);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnFormats(): array
    {
        $formats = [];
        foreach (['maSoThue', 'vonDieuLe', 'dienThoai'] as $key) {
            $letter = HopTacXaExcelColumns::columnLetterForKey($key);
            if ($letter !== null) {
                $formats[$letter] = NumberFormat::FORMAT_TEXT;
            }
        }

        return $formats;
    }
}
