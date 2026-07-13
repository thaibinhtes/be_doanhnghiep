<?php

namespace App\Support;

use App\Models\DoanhNghiep;
use App\Models\DoanhNghiepImportJobRow;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class DoanhNghiepImportProcessor
{
    /** @var array<int, array{row: int, message: string}> */
    private array $errors = [];

    private int $imported = 0;

    private int $duplicates = 0;

    private int $failed = 0;

    /** @var array<string, int> */
    private array $seenMsdnInFile = [];

    private ?DoanhNghiepImportRowRecorder $rowRecorder = null;

    public function __construct(
        private readonly ?User $user = null,
        /** @var array<string, string|array<string, mixed>>|null */
        private readonly ?array $valueExtensions = null,
        private readonly ?int $importJobId = null,
    ) {
        if ($this->importJobId !== null && $this->user !== null) {
            $this->rowRecorder = new DoanhNghiepImportRowRecorder($this->importJobId, $this->user->id);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function processRow(array $data, int $rowNumber): void
    {
        if ($this->isEmptyRow($data)) {
            return;
        }

        $data = DoanhNghiepImportExtensionHelper::apply($data, $this->valueExtensions);

        $tenDoanhNghiep = trim((string) ($data['tenDoanhNghiep'] ?? ''));
        $maSoDoanhNghiep = trim((string) ($data['maSoDoanhNghiep'] ?? ''));

        $validator = Validator::make($data, [
            'tenDoanhNghiep' => ['required', 'string', 'max:255'],
            'maSoDoanhNghiep' => ['nullable', 'string', 'max:50'],
            'tt' => ['nullable', 'integer'],
            'diaChi' => ['nullable', 'string'],
            'diaChiCu' => ['nullable', 'string'],
            'diaChiMoi' => ['nullable', 'string'],
            'long' => ['nullable', 'numeric', 'between:-180,180'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'quanHuyen' => ['nullable', 'string', 'max:100'],
            'phuongXa' => ['nullable', 'string', 'max:100'],
            'quanHuyenCu' => ['nullable', 'string', 'max:255'],
            'quanHuyenMoi' => ['nullable', 'string', 'max:255'],
            'phuongXaCu' => ['nullable', 'string', 'max:255'],
            'phuongXaMoi' => ['nullable', 'string', 'max:255'],
            'vonDieuLe' => ['nullable', 'string', 'max:100'],
            'trangThai' => ['nullable', 'string', 'max:100'],
            'daCapNhatDinhDanh' => ['nullable', 'boolean'],
            'dienThoai' => ['nullable', 'string', 'max:50'],
            'nguoiDaiDienTen' => ['nullable', 'string', 'max:255'],
            'ngaySinhNguoiDaiDien' => ['nullable', 'string', 'max:50'],
            'chuSoHuuTen' => ['nullable', 'string', 'max:255'],
            'nganhNgheKDChinh' => ['nullable', 'string', 'max:20'],
            'nganhNgheKD' => ['nullable', 'string'],
            'ngayCap' => ['nullable', 'string', 'max:50'],
            'ngayDangKyThayDoi' => ['nullable', 'string', 'max:50'],
            'loaiHinhDN' => ['nullable', 'string', 'max:100'],
            'soLuongLaoDong' => ['nullable', 'integer', 'min:0'],
            'loaiDN' => ['nullable', 'string', 'max:100'],
            'dsCoDong' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            $message = implode('; ', $validator->errors()->all());
            $this->recordFailure($rowNumber, $maSoDoanhNghiep ?: null, $tenDoanhNghiep ?: null, $message);

            return;
        }

        if ($maSoDoanhNghiep !== '' && isset($this->seenMsdnInFile[$maSoDoanhNghiep])) {
            $firstRow = $this->seenMsdnInFile[$maSoDoanhNghiep];
            $this->recordDuplicate(
                $rowNumber,
                $maSoDoanhNghiep,
                $tenDoanhNghiep,
                null,
                "Mã số doanh nghiệp trùng trong file (đã xử lý ở dòng {$firstRow}).",
            );

            return;
        }

        if ($maSoDoanhNghiep !== '') {
            $this->seenMsdnInFile[$maSoDoanhNghiep] = $rowNumber;
        }

        $membersText = $data['dsThanhVienGopVon'] ?? null;
        unset($data['dsThanhVienGopVon']);

        $data = $this->normalizeLegacyAddressAliases($data);

        $snakeData = DoanhNghiepExcelColumns::mapToSnake($data);
        $snakeData = DoanhNghiepNganhNgheHelper::apply($snakeData);

        if ($this->rowHasAddressFields($data)) {
            // Text địa bàn do linker ghi (match code / ghi chú), không lấy trực tiếp từ map.
            unset(
                $snakeData['dia_chi'],
                $snakeData['quan_huyen'],
                $snakeData['phuong_xa'],
            );

            $linked = (new DoanhNghiepHanhChinhImportLinker)->resolve($data);
            $snakeData = array_merge($snakeData, $linked['snake']);

            if ($linked['notes'] === []) {
                $snakeData['ghi_chu_hanh_chinh'] = null;
            }
        }

        try {
            $existing = null;

            if (!empty($snakeData['ma_so_doanh_nghiep'])) {
                $existing = DoanhNghiepScopeHelper::query($this->user)
                    ->where('ma_so_doanh_nghiep', $snakeData['ma_so_doanh_nghiep'])
                    ->first();

                if (!$existing && DoanhNghiep::query()
                    ->where('ma_so_doanh_nghiep', $snakeData['ma_so_doanh_nghiep'])
                    ->exists()) {
                    $this->recordFailure(
                        $rowNumber,
                        $snakeData['ma_so_doanh_nghiep'],
                        $tenDoanhNghiep,
                        'Doanh nghiệp không thuộc phạm vi đơn vị của bạn.',
                    );

                    return;
                }
            }

            if ($existing) {
                $existing->update($snakeData);
                $doanhNghiep = $existing;
                $this->recordDuplicate(
                    $rowNumber,
                    $snakeData['ma_so_doanh_nghiep'] ?? $maSoDoanhNghiep ?: null,
                    $tenDoanhNghiep,
                    $doanhNghiep->id,
                    'Doanh nghiệp đã tồn tại, đã cập nhật thông tin.',
                );
            } else {
                if ($this->user) {
                    $assignmentDonViId = DoanhNghiepScopeHelper::resolveAssignmentDonViId($this->user);
                    if ($assignmentDonViId === null) {
                        $this->recordFailure(
                            $rowNumber,
                            $maSoDoanhNghiep ?: null,
                            $tenDoanhNghiep ?: null,
                            'Tài khoản chưa gắn đơn vị, không thể import doanh nghiệp.',
                        );

                        return;
                    }

                    $snakeData['don_vi_id'] = $assignmentDonViId;
                    $snakeData['created_by_user_id'] = $this->user->id;
                }
                $doanhNghiep = DoanhNghiep::create($snakeData);
                $this->recordSuccess(
                    $rowNumber,
                    $snakeData['ma_so_doanh_nghiep'] ?? $maSoDoanhNghiep ?: null,
                    $tenDoanhNghiep,
                    $doanhNghiep->id,
                );
            }

            $danhSachTV = DoanhNghiepExcelColumns::parseMembersFromImport(
                is_string($membersText) ? $membersText : null
            );

            if (!empty($danhSachTV)) {
                $doanhNghiep->memberCompanies()->delete();
                $this->syncMembersToCompany($doanhNghiep, $danhSachTV);
            }
        } catch (\Throwable $e) {
            $this->recordFailure(
                $rowNumber,
                $maSoDoanhNghiep ?: null,
                $tenDoanhNghiep ?: null,
                $e->getMessage(),
            );
        }
    }

    public function flushRows(): void
    {
        $this->rowRecorder?->flush();
    }

    /**
     * @return array{imported: int, duplicates: int, updated: int, failed: int, errors: array<int, array{row: int, message: string}>}
     */
    public function getResult(): array
    {
        $this->flushRows();

        return [
            'imported' => $this->imported,
            'duplicates' => $this->duplicates,
            'updated' => $this->duplicates,
            'failed' => $this->failed,
            'errors' => $this->errors,
        ];
    }

    private function recordSuccess(
        int $rowNumber,
        ?string $maSoDoanhNghiep,
        ?string $tenDoanhNghiep,
        int $doanhNghiepId,
    ): void {
        $this->imported++;
        $this->rowRecorder?->record(
            $rowNumber,
            DoanhNghiepImportJobRow::STATUS_SUCCESS,
            $maSoDoanhNghiep,
            $tenDoanhNghiep,
            $doanhNghiepId,
            'Import thành công.',
        );
    }

    private function recordDuplicate(
        int $rowNumber,
        ?string $maSoDoanhNghiep,
        ?string $tenDoanhNghiep,
        ?int $doanhNghiepId,
        string $message,
    ): void {
        $this->duplicates++;
        $this->rowRecorder?->record(
            $rowNumber,
            DoanhNghiepImportJobRow::STATUS_DUPLICATE,
            $maSoDoanhNghiep,
            $tenDoanhNghiep,
            $doanhNghiepId,
            $message,
        );
    }

    private function recordFailure(
        int $rowNumber,
        ?string $maSoDoanhNghiep,
        ?string $tenDoanhNghiep,
        string $message,
    ): void {
        $this->failed++;
        $this->errors[] = [
            'row' => $rowNumber,
            'message' => $message,
        ];
        $this->rowRecorder?->record(
            $rowNumber,
            DoanhNghiepImportJobRow::STATUS_FAILED,
            $maSoDoanhNghiep,
            $tenDoanhNghiep,
            null,
            $message,
        );
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
     * Map cũ quanHuyen/phuongXa/diaChi → field cũ nếu chưa có.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeLegacyAddressAliases(array $data): array
    {
        if ($this->hasValue($data['diaChi'] ?? null) && !$this->hasValue($data['diaChiCu'] ?? null)) {
            $data['diaChiCu'] = $data['diaChi'];
        }
        if ($this->hasValue($data['quanHuyen'] ?? null) && !$this->hasValue($data['quanHuyenCu'] ?? null)) {
            $data['quanHuyenCu'] = $data['quanHuyen'];
        }
        if ($this->hasValue($data['phuongXa'] ?? null) && !$this->hasValue($data['phuongXaCu'] ?? null)) {
            $data['phuongXaCu'] = $data['phuongXa'];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function rowHasAddressFields(array $data): bool
    {
        foreach ([
            'quanHuyenCu',
            'quanHuyenMoi',
            'phuongXaCu',
            'phuongXaMoi',
            'diaChiCu',
            'diaChiMoi',
            'quanHuyen',
            'phuongXa',
            'diaChi',
        ] as $key) {
            if ($this->hasValue($data[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function hasValue(mixed $value): bool
    {
        return $value !== null && $value !== '';
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
