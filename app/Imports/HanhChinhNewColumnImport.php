<?php

namespace App\Imports;

use App\Support\HanhChinhImportColumnMap;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithColumnLimit;
use Maatwebsite\Excel\Concerns\WithStartRow;

class HanhChinhNewColumnImport implements ToCollection, WithStartRow, WithColumnLimit
{
    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    /** @var array<string, list<string>> */
    private readonly array $columnMap;

    /**
     * @param  array<string, list<string>>|null  $columnMap
     */
    public function __construct(
        private readonly int $dataStartRow = HanhChinhImportColumnMap::DEFAULT_START_ROW,
        ?array $columnMap = null,
    ) {
        $this->columnMap = HanhChinhImportColumnMap::resolveNew($columnMap);
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $rowArray = $row instanceof Collection ? $row->all() : (array) $row;

            $parsed = HanhChinhImportColumnMap::parseRow(
                $rowArray,
                $this->columnMap,
            );

            if (HanhChinhImportColumnMap::isEmptyNewRow($parsed)) {
                continue;
            }

            $this->rows[] = $parsed;
        }
    }

    public function startRow(): int
    {
        return $this->dataStartRow;
    }

    public function endColumn(): string
    {
        return HanhChinhImportColumnMap::NEW_IMPORT_END_COLUMN;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        return $this->rows;
    }
}
