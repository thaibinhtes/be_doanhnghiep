<?php

namespace App\Imports;

use App\Models\DoanhNghiep;
use App\Support\DoanhNghiepStatusHelper;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DoanhNghiepDinhDanhImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array{row: int, message: string}> */
    private array $errors = [];

    private int $updated = 0;

    private int $failed = 0;

    public function __construct()
    {
        // Keep Vietnamese headings as-is.
        \Maatwebsite\Excel\Imports\HeadingRowFormatter::default('none');
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $raw = $row->toArray();

            $msdn = trim((string) ($this->pickValue($raw, [
                'mã số doanh nghiệp',
                'ma so doanh nghiep',
                'ma_so_doanh_nghiep',
                'mã số dn',
                'ma so dn',
            ]) ?? ''));
            $tenDoanhNghiep = trim((string) ($this->pickValue($raw, [
                'tên doanh nghiệp',
                'ten doanh nghiep',
                'ten_doanh_nghiep',
            ]) ?? ''));
            $dinhDanhText = trim((string) ($this->pickValue($raw, [
                'định danh',
                'dinh danh',
                'trạng thái định danh',
                'trang thai dinh danh',
            ]) ?? ''));

            if ($msdn === '' && $tenDoanhNghiep === '' && $dinhDanhText === '') {
                continue;
            }

            if ($msdn === '') {
                $this->failed++;
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => 'Thiếu mã số doanh nghiệp.',
                ];
                continue;
            }

            $daCapNhatDinhDanh = $this->parseDinhDanhValue($dinhDanhText);
            if ($daCapNhatDinhDanh === null) {
                $this->failed++;
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => 'Giá trị cột "định danh" không hợp lệ. Dùng "định danh" hoặc "chưa định danh".',
                ];
                continue;
            }

            $company = DoanhNghiep::query()
                ->where('ma_so_doanh_nghiep', $msdn)
                ->first();

            if (!$company) {
                $this->failed++;
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => "Không tìm thấy doanh nghiệp với MSDN {$msdn}.",
                ];
                continue;
            }

            DoanhNghiepStatusHelper::syncDinhDanhStatus($company, $daCapNhatDinhDanh);
            $this->updated++;
        }
    }

    /**
     * @return array{imported: int, updated: int, failed: int, errors: array<int, array{row: int, message: string}>}
     */
    public function getResult(): array
    {
        return [
            'imported' => 0,
            'updated' => $this->updated,
            'failed' => $this->failed,
            'errors' => $this->errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function pickValue(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        return null;
    }

    private function parseDinhDanhValue(string $value): ?bool
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = preg_replace('/\s+/', ' ', $normalized ?? '');

        if ($normalized === null || $normalized === '') {
            return null;
        }

        $truthy = ['định danh', 'da dinh danh', 'đã định danh', 'true', '1', 'yes', 'x'];
        $falsy = ['chưa định danh', 'chua dinh danh', 'false', '0', 'no', ''];

        if (in_array($normalized, $truthy, true)) {
            return true;
        }

        if (in_array($normalized, $falsy, true)) {
            return false;
        }

        return null;
    }
}
