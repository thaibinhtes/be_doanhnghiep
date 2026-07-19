<?php

namespace App\Imports;

use App\Models\User;
use App\Support\DoanhNghiepFieldUpdateImportColumnMap;
use App\Support\DoanhNghiepFieldUpdateProcessor;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnLimit;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Row;

class DoanhNghiepFieldUpdateImport implements OnEachRow, WithChunkReading, WithColumnLimit, WithStartRow
{
    /** @var array<string, list<string>> */
    private readonly array $columnMap;

    private readonly DoanhNghiepFieldUpdateProcessor $processor;

    private readonly string $endColumn;

    /**
     * @param  array<string, list<string>>|null  $columnMap
     */
    public function __construct(
        private readonly ?User $user = null,
        private readonly int $dataStartRow = DoanhNghiepFieldUpdateImportColumnMap::DEFAULT_START_ROW,
        ?array $columnMap = null,
        private readonly string $lookupField = DoanhNghiepFieldUpdateImportColumnMap::DEFAULT_LOOKUP_FIELD,
        private readonly ?int $importJobId = null,
    ) {
        $this->columnMap = DoanhNghiepFieldUpdateImportColumnMap::resolve($columnMap);
        $this->endColumn = DoanhNghiepFieldUpdateImportColumnMap::resolveEndColumn($this->columnMap);
        $this->processor = new DoanhNghiepFieldUpdateProcessor(
            $this->user,
            $this->lookupField,
            $this->importJobId,
        );
    }

    public function onRow(Row $row): void
    {
        $parsed = DoanhNghiepFieldUpdateImportColumnMap::parseRow(
            $row->toArray(null, false, false, $this->endColumn()),
            $this->columnMap,
        );

        $this->processor->processRow($parsed, $row->getIndex());
    }

    /**
     * @return array{imported: int, updated: int, skipped: int, failed: int, errors: array<int, array{row: int, message: string}>}
     */
    public function getResult(): array
    {
        return $this->processor->getResult();
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function startRow(): int
    {
        return $this->dataStartRow;
    }

    public function endColumn(): string
    {
        return $this->endColumn;
    }
}
