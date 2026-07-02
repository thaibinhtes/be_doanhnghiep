<?php

namespace App\Support;

use App\Models\DoanhNghiep;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BaoCaoTienDoDinhDanhService
{
  private const DISSOLVED_STATUS_MAS = [
    'giai_the_pha_san',
    'tam_ngung',
    'khong_hoat_dong_dia_chi',
    'giai_the_hop_nhat',
    'thu_hoi_gcn',
  ];

  /**
   * @return array<string, mixed>
   */
  public function build(?Carbon $reportDate = null): array
  {
    $reportDate ??= now()->startOfDay();

    $ranges = [
      [
        'key' => 'before_2026',
        'label' => 'Từ trước đến 31/12/2025',
        'from' => null,
        'to' => '2025-12-31',
      ],
      [
        'key' => 'year_2026',
        'label' => 'Từ 01/01/2026 đến ' . $reportDate->format('d/m/Y'),
        'from' => '2026-01-01',
        'to' => $reportDate->toDateString(),
      ],
    ];

    $companies = DoanhNghiep::query()
      ->with('dnTrangThai')
      ->get();

    $rowDefinitions = [
      ['key' => 'doanh_nghiep', 'label' => 'Đối với doanh nghiệp', 'filter' => fn (DoanhNghiep $dn) => !$this->isHtx($dn)],
      ['key' => 'htx', 'label' => 'Đối với HTX/LH HTX', 'filter' => fn (DoanhNghiep $dn) => $this->isHtx($dn)],
    ];

    $rows = [];
    $totalsByRange = [];

    foreach ($ranges as $range) {
      $totalsByRange[$range['key']] = $this->emptyMetrics();
    }

    foreach ($rowDefinitions as $index => $definition) {
      $periods = [];

      foreach ($ranges as $range) {
        $metrics = $this->buildMetrics(
          $companies->filter($definition['filter']),
          $range['from'] ? Carbon::parse($range['from'])->startOfDay() : null,
          $range['to'] ? Carbon::parse($range['to'])->endOfDay() : null,
        );

        $periods[$range['key']] = $metrics;
        $totalsByRange[$range['key']] = $this->sumMetrics($totalsByRange[$range['key']], $metrics);
      }

      $rows[] = [
        'stt' => $index + 1,
        'key' => $definition['key'],
        'label' => $definition['label'],
        'periods' => $periods,
        'ghiChu' => null,
      ];
    }

    $rows[] = [
      'stt' => null,
      'key' => 'tong_cong',
      'label' => 'Tổng cộng',
      'periods' => $totalsByRange,
      'ghiChu' => null,
      'isTotal' => true,
    ];

    return [
      'title' => 'Biểu theo dõi tiến độ định danh tổ chức cho doanh nghiệp',
      'reportDate' => $reportDate->toDateString(),
      'reportDateLabel' => $this->formatVietnameseDate($reportDate),
      'ranges' => $ranges,
      'metricLabels' => $this->metricLabels(),
      'rows' => $rows,
      'generatedAt' => now()->toIso8601String(),
    ];
  }

  /**
   * @param  Collection<int, DoanhNghiep>  $companies
   * @return array<string, int>
   */
  private function buildMetrics(Collection $companies, ?Carbon $from, ?Carbon $to): array
  {
    $inRange = $companies->filter(function (DoanhNghiep $company) use ($from, $to) {
      return $this->isInDateRange($company->ngay_cap, $from, $to);
    });

    $soLuongCapGcn = $inRange->count();
    $donViGiaiThe = $inRange->filter(fn (DoanhNghiep $company) => $this->isDissolved($company))->count();
    $active = $inRange->reject(fn (DoanhNghiep $company) => $this->isDissolved($company));
    $canDinhDanh = $active->count();
    $daDinhDanh = $active->filter(fn (DoanhNghiep $company) => (bool) $company->da_cap_nhat_dinh_danh)->count();
    $chuaDinhDanh = max(0, $canDinhDanh - $daDinhDanh);

    return [
      'soLuongCapGcn' => $soLuongCapGcn,
      'donViGiaiThe' => $donViGiaiThe,
      'canDinhDanh' => $canDinhDanh,
      'daDinhDanh' => $daDinhDanh,
      'chuaDinhDanh' => $chuaDinhDanh,
    ];
  }

  private function isHtx(DoanhNghiep $company): bool
  {
    $loaiHinh = mb_strtolower(trim((string) $company->loai_hinh_dn));

    if ($loaiHinh === '') {
      return false;
    }

    return str_contains($loaiHinh, 'htx')
      || str_contains($loaiHinh, 'hợp tác xã')
      || str_contains($loaiHinh, 'liên hiệp');
  }

  private function isDissolved(DoanhNghiep $company): bool
  {
    $ma = $company->dnTrangThai?->ma;

    return $ma !== null && in_array($ma, self::DISSOLVED_STATUS_MAS, true);
  }

  private function isInDateRange(?string $ngayCap, ?Carbon $from, ?Carbon $to): bool
  {
    $date = $this->parseNgayCap($ngayCap);

    if (!$date) {
      return false;
    }

    if ($from && $date->lt($from)) {
      return false;
    }

    if ($to && $date->gt($to)) {
      return false;
    }

    return true;
  }

  private function parseNgayCap(?string $value): ?Carbon
  {
    if (!$value || trim($value) === '') {
      return null;
    }

    $value = trim($value);

    foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
      try {
        return Carbon::createFromFormat($format, $value)->startOfDay();
      } catch (\Throwable) {
        // try next format
      }
    }

    try {
      return Carbon::parse($value)->startOfDay();
    } catch (\Throwable) {
      return null;
    }
  }

  private function formatVietnameseDate(Carbon $date): string
  {
    return sprintf('ngày %d tháng %d năm %d', $date->day, $date->month, $date->year);
  }

  /**
   * @return array<string, string>
   */
  public function metricLabels(): array
  {
    return [
      'soLuongCapGcn' => 'Số lượng DN/HTX được cấp Giấy CN.ĐK DN',
      'donViGiaiThe' => 'Đơn vị giải thể, ngưng hoạt động',
      'canDinhDanh' => 'Số lượng doanh nghiệp cần định danh tổ chức',
      'daDinhDanh' => 'Số lượng doanh nghiệp đã định danh tổ chức',
      'chuaDinhDanh' => 'Số lượng doanh nghiệp chưa định danh tổ chức',
    ];
  }

  /**
   * @return array<string, int>
   */
  private function emptyMetrics(): array
  {
    return [
      'soLuongCapGcn' => 0,
      'donViGiaiThe' => 0,
      'canDinhDanh' => 0,
      'daDinhDanh' => 0,
      'chuaDinhDanh' => 0,
    ];
  }

  /**
   * @param  array<string, int>  $left
   * @param  array<string, int>  $right
   * @return array<string, int>
   */
  private function sumMetrics(array $left, array $right): array
  {
    $result = [];

    foreach (array_keys($this->emptyMetrics()) as $key) {
      $result[$key] = ($left[$key] ?? 0) + ($right[$key] ?? 0);
    }

    return $result;
  }
}
