<?php

namespace App\Support;

use App\Models\DoanhNghiep;
use App\Models\DoanhNghiepImportJobRow;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class DoanhNghiepFieldUpdateProcessor
{
    /** @var array<int, array{row: int, message: string}> */
    private array $errors = [];

    private int $updated = 0;

    private int $skipped = 0;

    private int $failed = 0;

    /** @var array<string, int> */
    private array $seenLookupValues = [];

    private ?DoanhNghiepImportRowRecorder $rowRecorder = null;

    public function __construct(
        private readonly ?User $user = null,
        private readonly string $lookupField = DoanhNghiepFieldUpdateImportColumnMap::DEFAULT_LOOKUP_FIELD,
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
        if (DoanhNghiepFieldUpdateImportColumnMap::isEmptyRow($data)) {
            return;
        }

        $lookupRaw = $data[$this->lookupField] ?? null;
        $lookupValue = is_string($lookupRaw) || is_numeric($lookupRaw)
            ? trim((string) $lookupRaw)
            : '';

        if ($lookupValue === '') {
            $this->recordFailure($rowNumber, null, null, 'Thiếu giá trị trường đối chiếu.');

            return;
        }

        if (isset($this->seenLookupValues[$lookupValue])) {
            $firstRow = $this->seenLookupValues[$lookupValue];
            $this->recordFailure(
                $rowNumber,
                $lookupValue,
                null,
                "Giá trị đối chiếu trùng trong file (đã xử lý ở dòng {$firstRow}).",
            );

            return;
        }

        $this->seenLookupValues[$lookupValue] = $rowNumber;

        $updatePayload = [];
        foreach ($data as $key => $value) {
            if ($key === $this->lookupField) {
                continue;
            }
            if (!DoanhNghiepFieldUpdateRegistry::isUpdateField($key)) {
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            $updatePayload[$key] = $value;
        }

        if ($updatePayload === []) {
            $this->skipped++;
            $this->rowRecorder?->record(
                $rowNumber,
                DoanhNghiepImportJobRow::STATUS_SUCCESS,
                $this->lookupField === 'maSoDoanhNghiep' ? $lookupValue : null,
                $this->lookupField === 'tenDoanhNghiep' ? $lookupValue : null,
                null,
                'Bỏ qua: không có field cập nhật (ô trống).',
            );

            return;
        }

        $dbColumn = DoanhNghiepFieldUpdateRegistry::lookupDbColumn($this->lookupField);
        if ($dbColumn === null) {
            $this->recordFailure($rowNumber, $lookupValue, null, 'Trường đối chiếu không hợp lệ.');

            return;
        }

        $matches = DoanhNghiepScopeHelper::query($this->user)
            ->where($dbColumn, $lookupValue)
            ->limit(2)
            ->get();

        if ($matches->isEmpty()) {
            $existsOutsideScope = DoanhNghiep::query()->where($dbColumn, $lookupValue)->exists();
            $message = $existsOutsideScope
                ? 'Doanh nghiệp tồn tại nhưng không thuộc phạm vi đơn vị của bạn.'
                : 'Không tìm thấy doanh nghiệp khớp trường đối chiếu.';
            $this->recordFailure($rowNumber, $lookupValue, null, $message);

            return;
        }

        if ($matches->count() > 1) {
            $this->recordFailure(
                $rowNumber,
                $lookupValue,
                null,
                'Tìm thấy nhiều doanh nghiệp khớp trường đối chiếu. Vui lòng dùng khóa duy nhất hơn.',
            );

            return;
        }

        /** @var DoanhNghiep $company */
        $company = $matches->first();

        $updatePayload = DoanhNghiepNganhNgheHelper::normalizeImportCamelRow($updatePayload);

        $validator = Validator::make($updatePayload, $this->validationRules(array_keys($updatePayload)));
        if ($validator->fails()) {
            $this->recordFailure(
                $rowNumber,
                $company->ma_so_doanh_nghiep,
                $company->ten_doanh_nghiep,
                implode('; ', $validator->errors()->all()),
            );

            return;
        }

        try {
            $snakeData = $this->buildUpdateSnake($company, $updatePayload);
            if ($snakeData === []) {
                $this->skipped++;
                $this->rowRecorder?->record(
                    $rowNumber,
                    DoanhNghiepImportJobRow::STATUS_SUCCESS,
                    $company->ma_so_doanh_nghiep,
                    $company->ten_doanh_nghiep,
                    $company->id,
                    'Bỏ qua: không có thay đổi sau chuẩn hóa.',
                );

                return;
            }

            $company->update($snakeData);
            $this->updated++;
            $this->rowRecorder?->record(
                $rowNumber,
                DoanhNghiepImportJobRow::STATUS_SUCCESS,
                $company->ma_so_doanh_nghiep,
                $company->ten_doanh_nghiep,
                $company->id,
                'Cập nhật field thành công.',
            );
        } catch (\Throwable $exception) {
            $this->recordFailure(
                $rowNumber,
                $company->ma_so_doanh_nghiep,
                $company->ten_doanh_nghiep,
                $exception->getMessage(),
            );
        }
    }

    /**
     * @return array{imported: int, updated: int, skipped: int, failed: int, errors: array<int, array{row: int, message: string}>}
     */
    public function getResult(): array
    {
        $this->rowRecorder?->flush();

        return [
            'imported' => 0,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'errors' => $this->errors,
        ];
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, list<string>>
     */
    private function validationRules(array $keys): array
    {
        $all = [
            'tenDoanhNghiep' => ['string', 'max:255'],
            'tinhThanhCu' => ['string', 'max:255'],
            'quanHuyenCu' => ['string', 'max:255'],
            'quanHuyenMoi' => ['string', 'max:255'],
            'phuongXaCu' => ['string', 'max:255'],
            'phuongXaMoi' => ['string', 'max:255'],
            'diaChiCu' => ['string'],
            'diaChiMoi' => ['string'],
            'vonDieuLe' => ['string', 'max:100'],
            'trangThai' => ['string', 'max:100'],
            'dienThoai' => ['string', 'max:50'],
            'nguoiDaiDienTen' => ['string', 'max:255'],
            'ngaySinhNguoiDaiDien' => ['string', 'max:50'],
            'chuSoHuuTen' => ['string', 'max:255'],
            'nganhNgheKDChinh' => ['string', 'max:'.DoanhNghiepNganhNgheHelper::CODE_MAX_LENGTH],
            'nganhNgheKD' => ['string'],
            'ngayCap' => ['string', 'max:50'],
            'ngayDangKyThayDoi' => ['string', 'max:50'],
            'loaiHinhDN' => ['string', 'max:100'],
            'soLuongLaoDong' => ['integer', 'min:0'],
            'dsCoDong' => ['string'],
            'loaiDN' => ['string', 'max:100'],
            'long' => ['numeric', 'between:-180,180'],
            'lat' => ['numeric', 'between:-90,90'],
        ];

        $rules = [];
        foreach ($keys as $key) {
            if (isset($all[$key])) {
                $rules[$key] = $all[$key];
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $updatePayload
     * @return array<string, mixed>
     */
    private function buildUpdateSnake(DoanhNghiep $company, array $updatePayload): array
    {
        $adminPayload = [];
        $scalarPayload = [];

        foreach ($updatePayload as $key => $value) {
            if (DoanhNghiepFieldUpdateRegistry::isAdminField($key)) {
                $adminPayload[$key] = $value;
            } else {
                $scalarPayload[$key] = $value;
            }
        }

        $snake = DoanhNghiepExcelColumns::mapToSnake($scalarPayload);
        $snake = DoanhNghiepNganhNgheHelper::apply($snake);
        $snake = DoanhNghiepLoaiHinhHelper::applyLoaiHinh($snake, $company);
        $snake = DoanhNghiepStatusHelper::applyStatus($snake, $company);

        if ($adminPayload !== []) {
            $textMapped = (new DoanhNghiepHanhChinhTextMapper)->mapForUpdate($company, $adminPayload);
            $snake = array_merge($snake, $textMapped);
        }

        return $snake;
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
}
