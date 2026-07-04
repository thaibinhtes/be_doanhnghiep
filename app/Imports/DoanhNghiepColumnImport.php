<?php

namespace App\Imports;

use App\Models\User;
use App\Support\DoanhNghiepImportColumnMap;
use App\Support\DoanhNghiepImportProcessor;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnLimit;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Row;

class DoanhNghiepColumnImport implements OnEachRow, WithChunkReading, WithColumnLimit, WithStartRow
{
    private DoanhNghiepImportProcessor $processor;

    private bool $stopped = false;

    /** @var array<string, list<string>> */
    private readonly array $columnMap;

    /**
     * @param  array<string, list<string>>|null  $columnMap
     * @param  array<string, string|array<string, mixed>>|null  $valueExtensions
     */
    public function __construct(
        ?User $user = null,
        private readonly int $dataStartRow = DoanhNghiepImportColumnMap::DEFAULT_START_ROW,
        ?array $columnMap = null,
        ?array $valueExtensions = null,
        private readonly ?int $importJobId = null,
    ) {
        $this->processor = new DoanhNghiepImportProcessor($user, $valueExtensions, $importJobId);
        $this->columnMap = DoanhNghiepImportColumnMap::resolve($columnMap);
    }

    public function onRow(Row $row): void
    {
        if ($this->stopped) {
            return;
        }

        $rowNumber = $row->getIndex();

        if ($rowNumber < $this->dataStartRow) {
            return;
        }

        $data = DoanhNghiepImportColumnMap::parseRow(
            $row->toArray(null, false, false, $this->endColumn()),
            $this->columnMap,
        );

        if (DoanhNghiepImportColumnMap::isEmptyRow($data)) {
            $this->stopped = true;

            return;
        }

        $this->processor->processRow($data, $rowNumber);
    }

    public function startRow(): int
    {
        return $this->dataStartRow;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function endColumn(): string
    {
        return 'AR';
    }

    /**
     * @return array{imported: int, duplicates: int, updated: int, failed: int, errors: array<int, array{row: int, message: string}>}
     */
    public function getResult(): array
    {
        return $this->processor->getResult();
    }
}
