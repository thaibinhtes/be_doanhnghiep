<?php

namespace App\Exports;

use App\Models\DanhMucNganhNghe;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DanhMucNganhNgheExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function collection(): Collection
    {
        return DanhMucNganhNghe::query()
            ->with('parent:id,ma')
            ->orderBy('cap')
            ->orderBy('thu_tu')
            ->orderBy('ma')
            ->get();
    }

    public function headings(): array
    {
        return [
            'cap',
            'parent_ma',
            'ma',
            'ten',
            'thu_tu',
            'is_active',
        ];
    }

    /**
     * @param  DanhMucNganhNghe  $item
     */
    public function map($item): array
    {
        return [
            $item->cap,
            $item->parent?->ma ?? '',
            $item->ma,
            $item->ten,
            $item->thu_tu,
            $item->is_active ? 1 : 0,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
