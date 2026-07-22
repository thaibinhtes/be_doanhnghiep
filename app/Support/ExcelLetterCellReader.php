<?php

namespace App\Support;

use Maatwebsite\Excel\Row;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Đọc ô Excel theo chữ cột (A, H, AA…) — không phụ thuộc thứ tự / index mảng toArray().
 */
class ExcelLetterCellReader
{
    /**
     * @param  list<string>  $letters
     */
    public static function firstNonEmpty(Row $row, array $letters): ?string
    {
        $worksheet = $row->getDelegate()->getWorksheet();
        $rowIndex = $row->getIndex();

        $ordered = $letters;
        usort(
            $ordered,
            static fn (string $left, string $right): int => self::letterIndex($left) <=> self::letterIndex($right),
        );

        foreach ($ordered as $letter) {
            $text = self::read($worksheet, $rowIndex, $letter);
            if ($text !== null && $text !== '') {
                return $text;
            }
        }

        return null;
    }

    public static function read(Worksheet $worksheet, int $rowIndex, string $letter): ?string
    {
        $letter = self::normalizeLetter($letter);
        if ($letter === '' || $rowIndex < 1) {
            return null;
        }

        $coordinate = $letter.$rowIndex;
        $text = self::cellText($worksheet, $coordinate);
        if ($text !== null && $text !== '') {
            return $text;
        }

        // Ô thuộc vùng merge: lấy giá trị ô gốc (góc trên-trái).
        try {
            $cell = $worksheet->getCell($coordinate);
            if ($cell->isInMergeRange()) {
                $range = $cell->getMergeRange();
                if (is_string($range) && $range !== '') {
                    $blocks = Coordinate::splitRange($range);
                    $master = $blocks[0][0] ?? null;
                    if (is_string($master) && strtoupper($master) !== strtoupper($coordinate)) {
                        $text = self::cellText($worksheet, $master);
                        if ($text !== null && $text !== '') {
                            return $text;
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // ignore merge lookup errors
        }

        return null;
    }

    private static function cellText(Worksheet $worksheet, string $coordinate): ?string
    {
        try {
            $cell = $worksheet->getCell($coordinate);
        } catch (\Throwable) {
            return null;
        }

        $value = $cell->getValue();

        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof RichText) {
            $value = $value->getPlainText();
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_float($value) || is_int($value)) {
            if (is_float($value) && floor($value) === $value) {
                return (string) (int) $value;
            }

            return is_int($value)
                ? (string) $value
                : rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    private static function normalizeLetter(string $column): string
    {
        return strtoupper(preg_replace('/[^A-Za-z]/', '', $column) ?? '');
    }

    private static function letterIndex(string $letter): int
    {
        $letter = self::normalizeLetter($letter);
        if ($letter === '') {
            return -1;
        }

        $index = 0;
        for ($i = 0, $len = strlen($letter); $i < $len; $i++) {
            $index = ($index * 26) + (ord($letter[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }
}
