<?php

namespace App\Imports;

use App\Support\TaxImportColumnMap;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\RemembersChunkOffset;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnLimit;
use Maatwebsite\Excel\Concerns\WithStartRow;

class CompanyTaxColumnImport implements ToCollection, WithStartRow, WithColumnLimit, WithChunkReading
{
    use RemembersChunkOffset;

    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    /** @var array<string, list<string>> */
    private readonly array $columnMap;

    /**
     * @var (callable(array<string, mixed>, int): void)|null
     */
    private $onRow;

    private int $processedRows = 0;

    /**
     * @param  array<string, list<string>>|null  $columnMap
     * @param  (callable(array<string, mixed>, int): void)|null  $onRow
     */
    public function __construct(
        private readonly int $dataStartRow = TaxImportColumnMap::DEFAULT_START_ROW,
        ?array $columnMap = null,
        ?callable $onRow = null,
    ) {
        $this->columnMap = TaxImportColumnMap::resolveCompanyTax($columnMap);
        $this->onRow = $onRow;
    }

    public function collection(Collection $rows): void
    {
        $chunkOffset = method_exists($this, 'getChunkOffset') ? (int) $this->getChunkOffset() : 0;
        foreach ($rows as $index => $row) {
            $rowArray = $row instanceof Collection ? $row->all() : (array) $row;
            $parsed = TaxImportColumnMap::parseRow($rowArray, $this->columnMap);

            if (TaxImportColumnMap::isEmptyCompanyTaxRow($parsed)) {
                continue;
            }

            $excelRow = $this->dataStartRow + $chunkOffset + (int) $index;
            $this->processedRows++;

            if ($this->onRow !== null) {
                ($this->onRow)($parsed, $excelRow);
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
        return TaxImportColumnMap::COMPANY_TAX_END_COLUMN;
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

    public function processedRows(): int
    {
        return $this->processedRows;
    }
}
