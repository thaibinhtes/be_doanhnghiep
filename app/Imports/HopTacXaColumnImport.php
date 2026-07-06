<?php

namespace App\Imports;

use App\Models\User;
use App\Support\HopTacXaImportColumnMap;
use App\Support\HopTacXaImportProcessor;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnLimit;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Row;

class HopTacXaColumnImport implements OnEachRow, WithChunkReading, WithColumnLimit, WithStartRow
{
    private HopTacXaImportProcessor $processor;

    private bool $stopped = false;

    /** @var array<string, list<string>> */
    private readonly array $columnMap;

    /**
     * @param  array<string, list<string>>|null  $columnMap
     */
    public function __construct(
        ?User $user = null,
        private readonly int $dataStartRow = HopTacXaImportColumnMap::DEFAULT_START_ROW,
        ?array $columnMap = null,
        private readonly ?int $importJobId = null,
    ) {
        $this->processor = new HopTacXaImportProcessor($user, $importJobId);
        $this->columnMap = HopTacXaImportColumnMap::resolve($columnMap);
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

        $data = HopTacXaImportColumnMap::parseRow(
            $row->toArray(null, false, false, $this->endColumn()),
            $this->columnMap,
        );

        if (HopTacXaImportColumnMap::isEmptyRow($data)) {
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
        return HopTacXaImportColumnMap::IMPORT_END_COLUMN;
    }

    /**
     * @return array{imported: int, duplicates: int, updated: int, failed: int, errors: array<int, array{row: int, message: string}>}
     */
    public function getResult(): array
    {
        return $this->processor->getResult();
    }
}
