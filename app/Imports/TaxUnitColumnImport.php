<?php

namespace App\Imports;

use App\Support\TaxImportColumnMap;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnLimit;
use Maatwebsite\Excel\Concerns\WithStartRow;

class TaxUnitColumnImport implements ToCollection, WithStartRow, WithColumnLimit, WithChunkReading
{
    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    /** @var array<string, list<string>> */
    private readonly array $columnMap;

    /**
     * @param  array<string, list<string>>|null  $columnMap
     */
    public function __construct(
        private readonly int $dataStartRow = TaxImportColumnMap::DEFAULT_START_ROW,
        ?array $columnMap = null,
    ) {
        $this->columnMap = TaxImportColumnMap::resolveTaxUnit($columnMap);
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $rowArray = $row instanceof Collection ? $row->all() : (array) $row;
            $parsed = TaxImportColumnMap::parseRow($rowArray, $this->columnMap);

            if (TaxImportColumnMap::isEmptyTaxUnitRow($parsed)) {
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
        return TaxImportColumnMap::TAX_UNIT_END_COLUMN;
    }

    public function chunkSize(): int
    {
        return 100;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        return $this->rows;
    }
}
