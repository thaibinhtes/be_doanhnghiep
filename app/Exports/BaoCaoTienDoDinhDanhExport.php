<?php

namespace App\Exports;

use App\Support\BaoCaoTienDoDinhDanhService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BaoCaoTienDoDinhDanhExport implements FromArray, ShouldAutoSize, WithStyles, WithEvents
{
    private array $report;

    private int $lastColumnIndex;

    public function __construct(
        private readonly BaoCaoTienDoDinhDanhService $service,
        private readonly array $options = [],
    ) {
        $this->report = $this->service->build($this->options);
        $this->lastColumnIndex = 2 + (count($this->report['ranges']) * 5) + 1;
    }

    public function array(): array
    {
        $metricKeys = array_keys($this->report['metricLabels']);
        $metricLabels = array_values($this->report['metricLabels']);

        $titleRow = array_fill(0, $this->lastColumnIndex, '');
        $titleRow[0] = mb_strtoupper($this->report['title'], 'UTF-8');

        $dateRow = array_fill(0, $this->lastColumnIndex, '');
        $dateRow[0] = $this->report['reportDateLabel'];

        $headerRow1 = ['Stt', 'Loại hình'];
        foreach ($this->report['ranges'] as $range) {
            $headerRow1[] = $range['label'];
            for ($i = 1; $i < 5; $i++) {
                $headerRow1[] = '';
            }
        }
        $headerRow1[] = 'Ghi chú';

        $headerRow2 = ['', ''];
        foreach ($this->report['ranges'] as $_range) {
            foreach ($metricLabels as $label) {
                $headerRow2[] = $label;
            }
        }
        $headerRow2[] = '';

        $dataRows = [];
        foreach ($this->report['rows'] as $row) {
            $line = [
                $row['stt'] !== null ? (string) $row['stt'] : '',
                $row['label'],
            ];

            foreach ($this->report['ranges'] as $range) {
                $metrics = $row['periods'][$range['key']] ?? [];
                foreach ($metricKeys as $key) {
                    $line[] = (string) ($metrics[$key] ?? 0);
                }
            }

            $line[] = $row['ghiChu'] ?? '';
            $dataRows[] = $line;
        }

        return [$titleRow, $dateRow, $headerRow1, $headerRow2, ...$dataRows];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastColumn = $this->columnLetter($this->lastColumnIndex);
        $headerRow = 4;
        $firstDataRow = 5;
        $lastDataRow = $firstDataRow + count($this->report['rows']) - 1;

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 14],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            2 => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            3 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFFFFF00'],
                ],
                'alignment' => [
                    'wrapText' => true,
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            4 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFFFFF00'],
                ],
                'alignment' => [
                    'wrapText' => true,
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            "A{$firstDataRow}:{$lastColumn}{$lastDataRow}" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $this->columnLetter($this->lastColumnIndex);
                $lastDataRow = 4 + count($this->report['rows']);

                $sheet->mergeCells("A1:{$lastColumn}1");
                $sheet->mergeCells("A2:{$lastColumn}2");
                $sheet->mergeCells('A3:A4');
                $sheet->mergeCells('B3:B4');
                $sheet->mergeCells("{$lastColumn}3:{$lastColumn}4");

                $column = 3;
                foreach ($this->report['ranges'] as $_range) {
                    $start = $this->columnLetter($column);
                    $end = $this->columnLetter($column + 4);
                    $sheet->mergeCells("{$start}3:{$end}3");
                    $column += 5;
                }

                $sheet->getStyle("A1:{$lastColumn}{$lastDataRow}")->applyFromArray([
                    'borders' => $this->borderStyle(),
                ]);

                $totalRow = $lastDataRow;
                $sheet->getStyle("A{$totalRow}:{$lastColumn}{$totalRow}")->getFont()->setBold(true);

                $metricKeys = array_keys($this->report['metricLabels']);
                $column = 3;
                foreach ($this->report['ranges'] as $_range) {
                    $chuaCol = $this->columnLetter($column + 4);
                    if (in_array('chuaDinhDanh', $metricKeys, true)) {
                        $sheet->getStyle("{$chuaCol}5:{$chuaCol}{$lastDataRow}")
                            ->getFont()
                            ->setColor(new Color(Color::COLOR_RED));
                    }
                    $column += 5;
                }
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
