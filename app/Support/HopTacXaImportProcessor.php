<?php

namespace App\Support;

use App\Models\HopTacXa;
use App\Models\HopTacXaImportJobRow;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class HopTacXaImportProcessor
{
    /** @var array<int, array{row: int, message: string}> */
    private array $errors = [];

    private int $imported = 0;

    private int $duplicates = 0;

    private int $failed = 0;

    /** @var array<string, int> */
    private array $seenMaSoThueInFile = [];

    private ?HopTacXaImportRowRecorder $rowRecorder = null;

    public function __construct(
        private readonly ?User $user = null,
        private readonly ?int $importJobId = null,
    ) {
        if ($this->importJobId !== null && $this->user !== null) {
            $this->rowRecorder = new HopTacXaImportRowRecorder($this->importJobId, $this->user->id);
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

        $tenHtx = trim((string) ($data['tenHtx'] ?? ''));
        $maSoThue = trim((string) ($data['maSoThue'] ?? ''));

        $validator = Validator::make($data, [
            'tenHtx' => ['required', 'string', 'max:255'],
            'maSoThue' => ['nullable', 'string', 'max:50'],
            'tt' => ['nullable', 'integer'],
            'namThanhLap' => ['nullable', 'string', 'max:10'],
            'chuTichHdqtTen' => ['nullable', 'string', 'max:255'],
            'dienThoai' => ['nullable', 'string', 'max:50'],
            'diaChi' => ['nullable', 'string'],
            'phuongXa' => ['nullable', 'string', 'max:150'],
            'diaChiCu' => ['nullable', 'string'],
            'diaChiMoi' => ['nullable', 'string'],
            'phuongXaCu' => ['nullable', 'string', 'max:255'],
            'phuongXaMoi' => ['nullable', 'string', 'max:255'],
            'quanHuyenCu' => ['nullable', 'string', 'max:255'],
            'quanHuyenMoi' => ['nullable', 'string', 'max:255'],
            'tinhThanhCu' => ['nullable', 'string', 'max:255'],
            'dienTichHa' => ['nullable', 'numeric', 'min:0'],
            'vonDieuLe' => ['nullable', 'string', 'max:100'],
            'soThanhVien' => ['nullable', 'integer', 'min:0'],
            'soNguoiLaoDong' => ['nullable', 'integer', 'min:0'],
            'linhVuc' => ['nullable', 'string', 'max:255'],
            'hoatDong' => ['nullable', 'string', 'max:255'],
            'dsThanhVien' => ['nullable', 'string'],
            'ghiChu' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            $message = implode('; ', $validator->errors()->all());
            $this->recordFailure($rowNumber, $maSoThue ?: null, $tenHtx ?: null, $message);

            return;
        }

        if ($maSoThue !== '' && isset($this->seenMaSoThueInFile[$maSoThue])) {
            $firstRow = $this->seenMaSoThueInFile[$maSoThue];
            $this->recordDuplicate(
                $rowNumber,
                $maSoThue,
                $tenHtx,
                null,
                "Mã số thuế trùng trong file (đã xử lý ở dòng {$firstRow}).",
            );

            return;
        }

        if ($maSoThue !== '') {
            $this->seenMaSoThueInFile[$maSoThue] = $rowNumber;
        }

        $snakeData = HopTacXaExcelColumns::mapToSnake($data);
        if (!empty($snakeData['dia_chi_cu']) && empty($snakeData['dia_chi'])) {
            $snakeData['dia_chi'] = $snakeData['dia_chi_cu'];
        }
        if (!empty($snakeData['xa_phuong_cu']) && empty($snakeData['phuong_xa'])) {
            $snakeData['phuong_xa'] = $snakeData['xa_phuong_cu'];
        }

        try {
            $existing = null;

            if (!empty($snakeData['ma_so_thue'])) {
                $existing = HopTacXaScopeHelper::query($this->user)
                    ->where('ma_so_thue', $snakeData['ma_so_thue'])
                    ->first();

                if (!$existing && HopTacXa::query()
                    ->where('ma_so_thue', $snakeData['ma_so_thue'])
                    ->exists()) {
                    $this->recordFailure(
                        $rowNumber,
                        $snakeData['ma_so_thue'],
                        $tenHtx,
                        'Hợp tác xã không thuộc phạm vi đơn vị của bạn.',
                    );

                    return;
                }
            }

            if ($existing) {
                $existing->update($snakeData);
                $hopTacXa = $existing;
                $this->recordDuplicate(
                    $rowNumber,
                    $snakeData['ma_so_thue'] ?? $maSoThue ?: null,
                    $tenHtx,
                    $hopTacXa->id,
                    'Hợp tác xã đã tồn tại, đã cập nhật thông tin.',
                );
            } else {
                if ($this->user) {
                    $assignmentDonViId = HopTacXaScopeHelper::resolveAssignmentDonViId($this->user);
                    if ($assignmentDonViId === null) {
                        $this->recordFailure(
                            $rowNumber,
                            $maSoThue ?: null,
                            $tenHtx ?: null,
                            'Tài khoản chưa gắn đơn vị, không thể import hợp tác xã.',
                        );

                        return;
                    }

                    $snakeData['don_vi_id'] = $assignmentDonViId;
                    $snakeData['created_by_user_id'] = $this->user->id;
                }

                $hopTacXa = HopTacXa::create($snakeData);
                $this->recordSuccess(
                    $rowNumber,
                    $snakeData['ma_so_thue'] ?? $maSoThue ?: null,
                    $tenHtx,
                    $hopTacXa->id,
                );
            }
        } catch (\Throwable $e) {
            $this->recordFailure(
                $rowNumber,
                $maSoThue ?: null,
                $tenHtx ?: null,
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function isEmptyRow(array $data): bool
    {
        return HopTacXaImportColumnMap::isEmptyRow($data);
    }

    private function recordSuccess(
        int $rowNumber,
        ?string $maSoThue,
        ?string $tenHtx,
        int $hopTacXaId,
    ): void {
        $this->imported++;
        $this->rowRecorder?->record(
            $rowNumber,
            HopTacXaImportJobRow::STATUS_SUCCESS,
            $maSoThue,
            $tenHtx,
            $hopTacXaId,
            'Import thành công.',
        );
    }

    private function recordDuplicate(
        int $rowNumber,
        ?string $maSoThue,
        ?string $tenHtx,
        ?int $hopTacXaId,
        string $message,
    ): void {
        $this->duplicates++;
        $this->rowRecorder?->record(
            $rowNumber,
            HopTacXaImportJobRow::STATUS_DUPLICATE,
            $maSoThue,
            $tenHtx,
            $hopTacXaId,
            $message,
        );
    }

    private function recordFailure(
        int $rowNumber,
        ?string $maSoThue,
        ?string $tenHtx,
        string $message,
    ): void {
        $this->failed++;
        $this->errors[] = ['row' => $rowNumber, 'message' => $message];
        $this->rowRecorder?->record(
            $rowNumber,
            HopTacXaImportJobRow::STATUS_FAILED,
            $maSoThue,
            $tenHtx,
            null,
            $message,
        );
    }
}
