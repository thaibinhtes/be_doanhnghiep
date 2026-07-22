<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use XMLReader;
use ZipArchive;

class ImportExcelKindDetector
{
    public const KIND_HTX = 'hop-tac-xa';

    public const KIND_DOANH_NGHIEP = 'doanh-nghiep';

    private const SCAN_MAX_ROW = 15;

    private const SCAN_MAX_COL = 26;

    /** Prefer streaming for workbooks at/above this size (bytes). */
    private const LARGE_XLSX_BYTES = 2_000_000;

    /** @var list<string> */
    private const HTX_MARKERS = [
        'ten htx',
        'tên htx',
        'hop tac xa',
        'hợp tác xã',
        'ct hđqt',
        'ct hdqt',
        'chu tich hdqt',
        'dien tich (ha)',
        'diện tích (ha)',
        'thanh vien (chi tiet)',
        'thành viên (chi tiết)',
    ];

    /** @var list<string> */
    private const DN_MARKERS = [
        'ten doanh nghiep',
        'tên doanh nghiệp',
        'ma so doanh nghiep',
        'mã số doanh nghiệp',
        'nguoi dai dien',
        'người đại diện',
        'chu so huu',
        'chủ sở hữu',
        'loai hinh dn',
        'loại hình dn',
        'nganh nghe',
        'ngành nghề',
    ];

    public static function detectFromPath(string $absolutePath): ?string
    {
        if (!is_file($absolutePath)) {
            return null;
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $size = (int) filesize($absolutePath);

        // Large xlsx: avoid PhpSpreadsheet (loads all sharedStrings → OOM under 128MB).
        if (in_array($extension, ['xlsx', 'xlsm'], true) && $size >= self::LARGE_XLSX_BYTES) {
            return self::detectFromXlsxStream($absolutePath);
        }

        return self::detectWithPhpSpreadsheet($absolutePath);
    }

    private static function detectWithPhpSpreadsheet(string $absolutePath): ?string
    {
        $previousLimit = ini_get('memory_limit');
        @ini_set('memory_limit', '256M');

        try {
            $reader = IOFactory::createReaderForFile($absolutePath);
            $reader->setReadDataOnly(true);
            if (method_exists($reader, 'setReadEmptyCells')) {
                $reader->setReadEmptyCells(false);
            }
            if (method_exists($reader, 'setReadFilter')) {
                $reader->setReadFilter(new class (self::SCAN_MAX_ROW, self::SCAN_MAX_COL) implements IReadFilter {
                    public function __construct(
                        private readonly int $maxRow,
                        private readonly int $maxCol,
                    ) {}

                    // Signature must match PhpSpreadsheet 1.x IReadFilter (untyped params).
                    public function readCell($columnAddress, $row, $worksheetName = '')
                    {
                        $row = (int) $row;
                        if ($row < 1 || $row > $this->maxRow) {
                            return false;
                        }

                        $colIndex = Coordinate::columnIndexFromString((string) $columnAddress);

                        return $colIndex >= 1 && $colIndex <= $this->maxCol;
                    }
                });
            }

            $spreadsheet = $reader->load($absolutePath);
            $sheet = $spreadsheet->getActiveSheet();

            $htxScore = 0;
            $dnScore = 0;

            for ($row = 1; $row <= self::SCAN_MAX_ROW; $row++) {
                for ($colIndex = 0; $colIndex < self::SCAN_MAX_COL; $colIndex++) {
                    $col = self::columnIndexToLetter($colIndex);
                    self::scoreText(self::normalize($sheet->getCell($col.$row)->getValue()), $htxScore, $dnScore);
                }
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $reader);

            return self::decide($htxScore, $dnScore);
        } catch (\Throwable) {
            $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
            if (in_array($extension, ['xlsx', 'xlsm'], true)) {
                return self::detectFromXlsxStream($absolutePath);
            }

            return null;
        } finally {
            if (is_string($previousLimit) && $previousLimit !== '') {
                @ini_set('memory_limit', $previousLimit);
            }
        }
    }

    /**
     * Stream sheet1 + sharedStrings without loading the whole workbook into memory.
     */
    private static function detectFromXlsxStream(string $absolutePath): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($absolutePath) !== true) {
            return null;
        }

        try {
            $sheetName = self::resolveFirstWorksheetPath($zip);
            $zip->close();

            if ($sheetName === null) {
                return null;
            }

            $neededSharedIndexes = [];
            $inlineTexts = [];
            $pastHeaderRows = false;

            $sheetUri = 'zip://'.$absolutePath.'#'.$sheetName;
            $sheetReader = new XMLReader();
            if (! @$sheetReader->open($sheetUri)) {
                return null;
            }

            while ($sheetReader->read()) {
                if ($sheetReader->nodeType !== XMLReader::ELEMENT || $sheetReader->localName !== 'c') {
                    continue;
                }

                $ref = $sheetReader->getAttribute('r');
                if (! is_string($ref) || ! preg_match('/^([A-Z]+)(\d+)$/', $ref, $parts)) {
                    continue;
                }

                $row = (int) $parts[2];
                if ($row > self::SCAN_MAX_ROW) {
                    $pastHeaderRows = true;
                    // Keep reading a bit in case rows are out of order, but bail quickly.
                    if ($row > self::SCAN_MAX_ROW + 50) {
                        break;
                    }
                    continue;
                }

                $colIndex = Coordinate::columnIndexFromString($parts[1]);
                if ($colIndex < 1 || $colIndex > self::SCAN_MAX_COL) {
                    continue;
                }

                $type = $sheetReader->getAttribute('t');
                $cellXml = $sheetReader->readOuterXml();

                if ($type === 'inlineStr') {
                    if (preg_match('/<t[^>]*>(.*?)<\/t>/su', $cellXml, $tMatch)) {
                        $inlineTexts[] = html_entity_decode($tMatch[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
                    }
                    continue;
                }

                if ($type === 's' && preg_match('/<v>(\d+)<\/v>/', $cellXml, $vMatch)) {
                    $neededSharedIndexes[(int) $vMatch[1]] = true;
                }
            }
            $sheetReader->close();

            if ($pastHeaderRows === false && $neededSharedIndexes === [] && $inlineTexts === []) {
                return null;
            }

            $sharedValues = self::readNeededSharedStrings($absolutePath, $neededSharedIndexes);

            $htxScore = 0;
            $dnScore = 0;

            foreach ($inlineTexts as $text) {
                self::scoreText(self::normalize($text), $htxScore, $dnScore);
            }
            foreach ($sharedValues as $text) {
                self::scoreText(self::normalize($text), $htxScore, $dnScore);
            }

            return self::decide($htxScore, $dnScore);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function resolveFirstWorksheetPath(ZipArchive $zip): ?string
    {
        $candidates = [
            'xl/worksheets/sheet1.xml',
            'xl/worksheets/sheet.xml',
        ];

        foreach ($candidates as $candidate) {
            if ($zip->locateName($candidate) !== false) {
                return $candidate;
            }
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @param  array<int, true>  $neededIndexes
     * @return list<string>
     */
    private static function readNeededSharedStrings(string $absolutePath, array $neededIndexes): array
    {
        if ($neededIndexes === []) {
            return [];
        }

        $uri = 'zip://'.$absolutePath.'#xl/sharedStrings.xml';
        $reader = new XMLReader();
        if (! @$reader->open($uri)) {
            return [];
        }

        $values = [];
        $index = 0;
        $maxNeeded = max(array_keys($neededIndexes));

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si') {
                continue;
            }

            // Always consume the whole <si> node so the cursor doesn't descend into children.
            $siXml = $reader->readOuterXml();

            if (isset($neededIndexes[$index])) {
                $parts = [];
                if (preg_match_all('/<t[^>]*>(.*?)<\/t>/su', $siXml, $tMatches)) {
                    foreach ($tMatches[1] as $part) {
                        $parts[] = html_entity_decode($part, ENT_QUOTES | ENT_XML1, 'UTF-8');
                    }
                }
                $values[] = implode('', $parts);
            }

            if ($index >= $maxNeeded) {
                break;
            }

            $index++;
        }

        $reader->close();

        return $values;
    }

    private static function scoreText(string $text, int &$htxScore, int &$dnScore): void
    {
        if ($text === '') {
            return;
        }

        foreach (self::HTX_MARKERS as $marker) {
            if (str_contains($text, $marker)) {
                $htxScore++;
            }
        }

        foreach (self::DN_MARKERS as $marker) {
            if (str_contains($text, $marker)) {
                $dnScore++;
            }
        }
    }

    private static function decide(int $htxScore, int $dnScore): ?string
    {
        if ($htxScore >= 2 && $htxScore > $dnScore) {
            return self::KIND_HTX;
        }

        if ($dnScore >= 2 && $dnScore > $htxScore) {
            return self::KIND_DOANH_NGHIEP;
        }

        return null;
    }

    private static function normalize(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $text = mb_strtolower(trim((string) $value));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return is_string($ascii) && $ascii !== '' ? $ascii : $text;
    }

    private static function columnIndexToLetter(int $index): string
    {
        $columnIndex = $index + 1;
        $letter = '';

        while ($columnIndex > 0) {
            $columnIndex--;
            $letter = chr(65 + ($columnIndex % 26)).$letter;
            $columnIndex = intdiv($columnIndex, 26);
        }

        return $letter;
    }
}
