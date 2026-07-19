<?php

namespace App\Support;

use App\Models\DoanhNghiep;

/**
 * Lưu địa bàn doanh nghiệp dạng text (bước 1).
 * Liên kết mã danh mục hành chính để bước đồng bộ sau.
 */
class DoanhNghiepHanhChinhTextMapper
{
    /**
     * @var array<string, string>
     */
    public const CAMEL_TO_SNAKE = [
        'tinhThanhCu' => 'tinh_thanh_cu',
        'quanHuyenCu' => 'quan_huyen_cu',
        'quanHuyenMoi' => 'quan_huyen_moi',
        'phuongXaCu' => 'xa_phuong_cu',
        'phuongXaMoi' => 'xa_phuong_moi',
        'diaChiCu' => 'dia_chi_cu',
        'diaChiMoi' => 'dia_chi_moi',
    ];

    /**
     * Mã liên kết sẽ xóa khi text tương ứng được ghi (chờ sync lại).
     *
     * @var array<string, string>
     */
    private const TEXT_CLEARS_CODE = [
        'tinh_thanh_cu' => 'tinh_thanh_cu_code',
        'quan_huyen_cu' => 'quan_huyen_cu_code',
        'xa_phuong_cu' => 'xa_phuong_cu_code',
        'quan_huyen_moi' => 'tinh_thanh_code',
        'xa_phuong_moi' => 'xa_phuong_code',
    ];

    /**
     * @param  array<string, mixed>  $data  camelCase row / payload
     * @return array<string, mixed>  snake_case columns to persist
     */
    public function map(array $data): array
    {
        $snake = [];

        foreach (self::CAMEL_TO_SNAKE as $camel => $column) {
            if (!array_key_exists($camel, $data)) {
                continue;
            }

            $value = trim((string) ($data[$camel] ?? ''));
            $snake[$column] = $value === '' ? null : $value;
        }

        foreach (self::TEXT_CLEARS_CODE as $textColumn => $codeColumn) {
            if (array_key_exists($textColumn, $snake)) {
                $snake[$codeColumn] = null;
            }
        }

        $this->applyLegacyDisplayFields($snake);

        return $snake;
    }

    /**
     * Chỉ ghi các field hành chính có trong payload cập nhật từng phần.
     *
     * @param  array<string, mixed>  $data  camelCase partial update
     * @return array<string, mixed>
     */
    public function mapForUpdate(DoanhNghiep $company, array $data): array
    {
        $snake = $this->map($data);

        if ($snake === []) {
            return [];
        }

        // Refresh legacy display từ bản ghi + text mới.
        $merged = [
            'dia_chi_cu' => array_key_exists('dia_chi_cu', $snake) ? $snake['dia_chi_cu'] : $company->dia_chi_cu,
            'dia_chi_moi' => array_key_exists('dia_chi_moi', $snake) ? $snake['dia_chi_moi'] : $company->dia_chi_moi,
            'quan_huyen_cu' => array_key_exists('quan_huyen_cu', $snake) ? $snake['quan_huyen_cu'] : $company->quan_huyen_cu,
            'quan_huyen_moi' => array_key_exists('quan_huyen_moi', $snake) ? $snake['quan_huyen_moi'] : $company->quan_huyen_moi,
            'xa_phuong_cu' => array_key_exists('xa_phuong_cu', $snake) ? $snake['xa_phuong_cu'] : $company->xa_phuong_cu,
            'xa_phuong_moi' => array_key_exists('xa_phuong_moi', $snake) ? $snake['xa_phuong_moi'] : $company->xa_phuong_moi,
        ];

        $display = [];
        $this->applyLegacyDisplayFields($merged);
        foreach (['dia_chi', 'quan_huyen', 'phuong_xa'] as $legacy) {
            if (array_key_exists($legacy, $merged)) {
                $display[$legacy] = $merged[$legacy];
            }
        }

        return array_merge($snake, $display);
    }

    /**
     * @param  array<string, mixed>  $snake
     */
    private function applyLegacyDisplayFields(array &$snake): void
    {
        $diaMoi = $snake['dia_chi_moi'] ?? null;
        $diaCu = $snake['dia_chi_cu'] ?? null;
        if (is_string($diaMoi) && $diaMoi !== '') {
            $snake['dia_chi'] = $diaMoi;
        } elseif (is_string($diaCu) && $diaCu !== '') {
            $snake['dia_chi'] = $diaCu;
        }

        $quanMoi = $snake['quan_huyen_moi'] ?? null;
        $quanCu = $snake['quan_huyen_cu'] ?? null;
        if (is_string($quanMoi) && $quanMoi !== '') {
            $snake['quan_huyen'] = $quanMoi;
        } elseif (is_string($quanCu) && $quanCu !== '') {
            $snake['quan_huyen'] = $quanCu;
        }

        $xaMoi = $snake['xa_phuong_moi'] ?? null;
        $xaCu = $snake['xa_phuong_cu'] ?? null;
        if (is_string($xaMoi) && $xaMoi !== '') {
            $snake['phuong_xa'] = $xaMoi;
        } elseif (is_string($xaCu) && $xaCu !== '') {
            $snake['phuong_xa'] = $xaCu;
        }
    }
}
