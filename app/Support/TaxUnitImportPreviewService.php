<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class TaxUnitImportPreviewService
{
    public const DEFAULT_LIMIT = 5;

    public const MAX_LIMIT = 20;

    private const EMPTY_ROW_BUFFER = 80;

    /**
     * @param  array<string, list<string>>|null  $columnMap
     * @return array<string, mixed>
     */
    public function preview(
        string $absolutePath,
        int $startRow = TaxImportColumnMap::DEFAULT_START_ROW,
        ?array $columnMap = null,
        int $limit = self::DEFAULT_LIMIT,
    ): array {
        $startRow = max(1, $startRow);
        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $resolvedMap = TaxImportColumnMap::resolveTaxUnit($columnMap);
        $labels = TaxExcelColumns::taxUnitColumnLabels();

        $endColumn = TaxImportColumnMap::resolveEndColumn($resolvedMap);
        $maxCol = Coordinate::columnIndexFromString($endColumn);
        $endRow = $startRow + $limit + self::EMPTY_ROW_BUFFER;

        $previousLimit = ini_get('memory_limit');
        @ini_set('memory_limit', '512M');

        $spreadsheet = null;

        try {
            $spreadsheet = $this->loadPreviewWindow($absolutePath, $startRow, $endRow, $maxCol);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $this->estimateHighestRow($absolutePath)
                ?? max((int) $sheet->getHighestRow(), $endRow);

            $rows = [];
            $scanned = 0;

            for ($rowIndex = $startRow; $rowIndex <= $endRow; $rowIndex++) {
                $scanned++;
                $mapped = TaxImportColumnMap::parseWorksheetRow($sheet, $rowIndex, $resolvedMap);

                if (TaxImportColumnMap::isEmptyTaxUnitRow($mapped)) {
                    if ($rows !== []) {
                        break;
                    }

                    continue;
                }

                $cells = [];
                foreach ($resolvedMap as $field => $letters) {
                    $rawByLetter = [];
                    foreach ($letters as $letter) {
                        $rawByLetter[$letter] = ExcelLetterCellReader::read($sheet, $rowIndex, $letter);
                    }

                    $cells[] = [
                        'field' => $field,
                        'label' => $labels[$field] ?? $field,
                        'columns' => $letters,
                        'columnsDisplay' => implode(',', $letters),
                        'rawByLetter' => $rawByLetter,
                        'value' => $mapped[$field] ?? null,
                    ];
                }

                $rows[] = [
                    'excelRow' => $rowIndex,
                    'unitCode' => $mapped['unitCode'] ?? null,
                    'unitName' => $mapped['unitName'] ?? null,
                    'fields' => $cells,
                    'mapped' => $mapped,
                ];

                if (count($rows) >= $limit) {
                    break;
                }
            }

            return [
                'startRow' => $startRow,
                'limit' => $limit,
                'highestRow' => $highestRow,
                'scannedRows' => $scanned,
                'previewCount' => count($rows),
                'columnMap' => $resolvedMap,
                'columnLabels' => array_intersect_key($labels, $resolvedMap),
                'rows' => $rows,
            ];
        } finally {
            if ($spreadsheet instanceof Spreadsheet) {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
            }
            if (is_string($previousLimit) && $previousLimit !== '') {
                @ini_set('memory_limit', $previousLimit);
            }
        }
    }

    private function loadPreviewWindow(
        string $absolutePath,
        int $startRow,
        int $endRow,
        int $maxCol,
    ): Spreadsheet {
        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }

        if (method_exists($reader, 'setReadFilter')) {
            $reader->setReadFilter(new class ($startRow, $endRow, $maxCol) implements IReadFilter {
                public function __construct(
                    private readonly int $startRow,
                    private readonly int $endRow,
                    private readonly int $maxCol,
                ) {}

                public function readCell($columnAddress, $row, $worksheetName = '')
                {
                    $row = (int) $row;
                    if ($row < $this->startRow || $row > $this->endRow) {
                        return false;
                    }

                    $colIndex = Coordinate::columnIndexFromString((string) $columnAddress);

                    return $colIndex >= 1 && $colIndex <= $this->maxCol;
                }
            });
        }

        return $reader->load($absolutePath);
    }

    private function estimateHighestRow(string $absolutePath): ?int
    {
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if (! in_array($extension, ['xlsx', 'xlsm'], true) || ! class_exists(\ZipArchive::class)) {
            return null;
        }

        $zip = new \ZipArchive;
        if ($zip->open($absolutePath) !== true) {
            return null;
        }

        try {
            $sheetPath = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (is_string($name) && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                    $sheetPath = $name;
                    break;
                }
            }

            if ($sheetPath === null) {
                return null;
            }

            $stream = $zip->getStream($sheetPath);
            if ($stream === false) {
                return null;
            }

            $head = fread($stream, 8192);
            fclose($stream);

            if (! is_string($head)) {
                return null;
            }

            if (preg_match('/dimension[^>]*ref="[A-Z]+\d+:[A-Z]+(\d+)"/i', $head, $matches)) {
                return max(1, (int) $matches[1]);
            }

            if (preg_match('/dimension[^>]*ref="[A-Z]+(\d+)"/i', $head, $matches)) {
                return max(1, (int) $matches[1]);
            }

            return null;
        } finally {
            $zip->close();
        }
    }
}
