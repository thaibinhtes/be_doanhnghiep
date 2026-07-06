<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportExcelKindDetector
{
    public const KIND_HTX = 'hop-tac-xa';

    public const KIND_DOANH_NGHIEP = 'doanh-nghiep';

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

        try {
            $reader = IOFactory::createReaderForFile($absolutePath);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            $spreadsheet = $reader->load($absolutePath);
            $sheet = $spreadsheet->getActiveSheet();

            $htxScore = 0;
            $dnScore = 0;

            for ($row = 1; $row <= 15; $row++) {
                for ($colIndex = 0; $colIndex < 26; $colIndex++) {
                    $col = self::columnIndexToLetter($colIndex);
                    $text = self::normalize($sheet->getCell($col . $row)->getValue());

                    if ($text === '') {
                        continue;
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
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            if ($htxScore >= 2 && $htxScore > $dnScore) {
                return self::KIND_HTX;
            }

            if ($dnScore >= 2 && $dnScore > $htxScore) {
                return self::KIND_DOANH_NGHIEP;
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
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
            $letter = chr(65 + ($columnIndex % 26)) . $letter;
            $columnIndex = intdiv($columnIndex, 26);
        }

        return $letter;
    }
}
