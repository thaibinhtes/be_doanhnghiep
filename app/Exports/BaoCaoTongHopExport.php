<?php

namespace App\Exports;

use App\Support\BaoCaoTongHopService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BaoCaoTongHopExport implements FromArray, ShouldAutoSize, WithStyles, WithEvents
{
    private const TITLE = 'BÁO CÁO TỔNG HỢP';

    private array $report;

    private int $columnCount;

    public function __construct(
        private readonly BaoCaoTongHopService $service,
    ) {
        $this->report = $this->service->build();
        $this->columnCount = count($this->report['columns']) + 1;
    }

    public function array(): array
    {
        $headings = array_map(
            fn (string $heading) => mb_strtoupper($heading, 'UTF-8'),
            array_merge(['STT'], array_column($this->report['columns'], 'ten')),
        );

        $values = array_merge(
            [(string) $this->report['stt']],
            array_map(fn (array $col) => (string) $col['count'], $this->report['columns']),
        );

        $titleRow = array_fill(0, $this->columnCount, '');
        $titleRow[0] = self::TITLE;

        return [$titleRow, $headings, $values];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastColumn = $this->columnLetter($this->columnCount);

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 14],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            2 => [
                'font' => ['bold' => true],
                'alignment' => [
                    'wrapText' => true,
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            "A3:{$lastColumn}3" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $this->columnLetter($this->columnCount);
                $tableRange = "A2:{$lastColumn}3";

                $sheet->mergeCells("A1:{$lastColumn}1");
                $sheet->getRowDimension(1)->setRowHeight(30);
                $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => $this->borderStyle(),
                ]);

                $sheet->getStyle($tableRange)->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'wrapText' => true,
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => $this->borderStyle(),
                ]);

                $sheet->getStyle("A3:{$lastColumn}3")->applyFromArray([
                    'font' => ['bold' => false],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => $this->borderStyle(),
                ]);
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function borderStyle(): array
    {
        return [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'],
            ],
        ];
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }
}
