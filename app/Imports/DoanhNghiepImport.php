<?php

namespace App\Imports;

use App\Models\DoanhNghiep;
use App\Models\Member;
use App\Support\DoanhNghiepExcelColumns;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DoanhNghiepImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array{row: int, message: string}> */
    private array $errors = [];

    private int $imported = 0;

    private int $updated = 0;

    private int $failed = 0;

    public function __construct()
    {
        // Keep Vietnamese headings as-is (do not slugify).
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

                $validator = Validator::make($data, [
                    'tenDoanhNghiep' => ['required', 'string', 'max:255'],
                    'maSoDoanhNghiep' => ['nullable', 'string', 'max:50'],
                    'tt' => ['nullable', 'integer'],
                    'diaChi' => ['nullable', 'string'],
                    'long' => ['nullable', 'numeric', 'between:-180,180'],
                    'lat' => ['nullable', 'numeric', 'between:-90,90'],
                    'quanHuyen' => ['nullable', 'string', 'max:100'],
                    'phuongXa' => ['nullable', 'string', 'max:100'],
                    'vonDieuLe' => ['nullable', 'string', 'max:100'],
                    'trangThai' => ['nullable', 'string', 'max:100'],
                    'daCapNhatDinhDanh' => ['nullable', 'boolean'],
                    'dienThoai' => ['nullable', 'string', 'max:50'],
                    'nguoiDaiDienTen' => ['nullable', 'string', 'max:255'],
                    'ngaySinhNguoiDaiDien' => ['nullable', 'string', 'max:50'],
                    'chuSoHuuTen' => ['nullable', 'string', 'max:255'],
                    'nganhNgheKDChinh' => ['nullable', 'string', 'max:255'],
                    'nganhNgheKD' => ['nullable', 'string'],
                    'ngayCap' => ['nullable', 'string', 'max:50'],
                    'ngayDangKyThayDoi' => ['nullable', 'string', 'max:50'],
                    'loaiHinhDN' => ['nullable', 'string', 'max:100'],
                    'soLuongLaoDong' => ['nullable', 'integer', 'min:0'],
                    'loaiDN' => ['nullable', 'string', 'max:100'],
                    'dsCoDong' => ['nullable', 'string'],
                ]);

                if ($validator->fails()) {
                    $this->failed++;
                    $this->errors[] = [
                        'row' => $rowNumber,
                        'message' => implode('; ', $validator->errors()->all()),
                    ];
                    continue;
                }

                $membersText = $data['dsThanhVienGopVon'] ?? null;
                unset($data['dsThanhVienGopVon']);

                $snakeData = DoanhNghiepExcelColumns::mapToSnake($data);

                try {
                    $existing = null;

                    if (!empty($snakeData['ma_so_doanh_nghiep'])) {
                        $existing = DoanhNghiep::query()
                            ->where('ma_so_doanh_nghiep', $snakeData['ma_so_doanh_nghiep'])
                            ->first();
                    }

                    if ($existing) {
                        $existing->update($snakeData);
                        $doanhNghiep = $existing;
                        $this->updated++;
                    } else {
                        $doanhNghiep = DoanhNghiep::create($snakeData);
                        $this->imported++;
                    }

                    $danhSachTV = DoanhNghiepExcelColumns::parseMembersFromImport(
                        is_string($membersText) ? $membersText : null
                    );

                    if (!empty($danhSachTV)) {
                        $doanhNghiep->memberCompanies()->delete();
                        $this->syncMembersToCompany($doanhNghiep, $danhSachTV);
                    }
                } catch (\Throwable $e) {
                    $this->failed++;
                    $this->errors[] = [
                        'row' => $rowNumber,
                        'message' => $e->getMessage(),
                    ];
                }
            }
        });
    }

    /**
     * @return array{imported: int, updated: int, failed: int, errors: array<int, array{row: int, message: string}>}
     */
    public function getResult(): array
    {
        return [
            'imported' => $this->imported,
            'updated' => $this->updated,
            'failed' => $this->failed,
            'errors' => $this->errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isEmptyRow(array $data): bool
    {
        $meaningful = array_filter($data, fn ($value) => $value !== null && $value !== '');

        return empty($meaningful);
    }

    /**
     * @param  array<int, array<string, mixed>>  $danhSachTV
     */
    private function syncMembersToCompany(DoanhNghiep $doanhNghiep, array $danhSachTV): void
    {
        foreach ($danhSachTV as $item) {
            $fullName = trim($item['fullName'] ?? '');
            if ($fullName === '') {
                continue;
            }

            $memberId = $item['memberId'] ?? null;

            if (!$memberId) {
                $member = Member::firstOrCreate(
                    ['full_name' => $fullName],
                    ['cccd' => null, 'status' => true]
                );
                $memberId = $member->id;
            }

            $doanhNghiep->members()->attach($memberId, [
                'date_join' => $item['dateJoin'] ?? null,
                'position' => $item['position'] ?? null,
                'investment_amount' => $item['investmentAmount'] ?? null,
            ]);
        }
    }
}
