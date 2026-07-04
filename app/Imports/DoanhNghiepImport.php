<?php

namespace App\Imports;

use App\Models\User;
use App\Support\DoanhNghiepExcelColumns;
use App\Support\DoanhNghiepImportProcessor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DoanhNghiepImport implements ToCollection, WithHeadingRow
{
    private DoanhNghiepImportProcessor $processor;

    public function __construct(
        ?User $user = null,
        /** @var array<string, string|array<string, mixed>>|null */
        ?array $valueExtensions = null,
        private readonly ?int $importJobId = null,
    ) {
        $this->processor = new DoanhNghiepImportProcessor($user, $valueExtensions, $importJobId);
        \Maatwebsite\Excel\Imports\HeadingRowFormatter::default('none');
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function collection(Collection $rows): void
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;
                $data = DoanhNghiepExcelColumns::rowFromHeadings($row->toArray());

                if ($this->isEmptyRow($data)) {
                    continue;
                }

                $this->processor->processRow($data, $rowNumber);
            }
        });
    }

    /**
     * @return array{imported: int, duplicates: int, updated: int, failed: int, errors: array<int, array{row: int, message: string}>}
     */
    public function getResult(): array
    {
        return $this->processor->getResult();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isEmptyRow(array $data): bool
    {
        $meaningful = array_filter($data, fn ($value) => $value !== null && $value !== '');

        return empty($meaningful);
    }
}
