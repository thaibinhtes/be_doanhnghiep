<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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
     * @param  array{reportDate?: string|null, range1To?: string|null, range2From?: string|null, range2To?: string|null}  $options
     * @return array<string, mixed>
     */
    public function build(array $options = [], ?User $user = null): array
    {
        $reportDate = $this->parseDate($options['reportDate'] ?? null) ?? now()->startOfDay();
        $range1To = $this->parseDate($options['range1To'] ?? '2025-12-31') ?? Carbon::parse('2025-12-31')->startOfDay();
        $range2From = $this->parseDate($options['range2From'] ?? '2026-01-01') ?? Carbon::parse('2026-01-01')->startOfDay();
        $range2To = $this->parseDate($options['range2To'] ?? $reportDate->toDateString()) ?? $reportDate->copy();

        if ($range2From->gt($range2To)) {
            throw new InvalidArgumentException('Kỳ 2: ngày bắt đầu phải trước hoặc bằng ngày kết thúc.');
        }

        $ranges = [
            [
                'key' => 'range_1',
                'label' => 'Từ trước đến ' . $range1To->format('d/m/Y'),
                'from' => null,
                'to' => $range1To->toDateString(),
            ],
            [
                'key' => 'range_2',
                'label' => 'Từ ' . $range2From->format('d/m/Y') . ' đến ' . $range2To->format('d/m/Y'),
                'from' => $range2From->toDateString(),
                'to' => $range2To->toDateString(),
            ],
        ];

        $rowDefinitions = [
            ['key' => 'doanh_nghiep', 'label' => 'Đối với doanh nghiệp', 'entity' => 'doanh_nghiep'],
            ['key' => 'htx', 'label' => 'Đối với HTX/LH HTX', 'entity' => 'htx'],
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
                    $user,
                    $definition['entity'],
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
            'filters' => [
                'range1To' => $range1To->toDateString(),
                'range2From' => $range2From->toDateString(),
                'range2To' => $range2To->toDateString(),
            ],
            'ranges' => $ranges,
            'metricLabels' => $this->metricLabels(),
            'rows' => $rows,
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  'doanh_nghiep'|'htx'  $entity
     * @return array<string, int>
     */
    private function buildMetrics(?User $user, string $entity, ?Carbon $from, ?Carbon $to): array
    {
        $query = DoanhNghiepScopeHelper::query($user)
            ->leftJoin('dn_trang_thais', 'dn_trang_thais.id', '=', 'doanh_nghieps.dn_trang_thai_id');

        $this->applyEntityFilter($query, $entity);
        $this->applyNgayCapRange($query, $from, $to);

        $dissolvedList = implode(',', array_map(
            fn (string $ma) => "'".str_replace("'", "''", $ma)."'",
            self::DISSOLVED_STATUS_MAS,
        ));

        $row = $query
            ->toBase()
            ->selectRaw('COUNT(doanh_nghieps.id) as so_luong_cap_gcn')
            ->selectRaw(
                "COUNT(CASE WHEN dn_trang_thais.ma IN ({$dissolvedList}) THEN 1 END) as don_vi_giai_the",
            )
            ->selectRaw(
                "COUNT(CASE WHEN dn_trang_thais.ma IS NULL OR dn_trang_thais.ma NOT IN ({$dissolvedList}) THEN 1 END) as can_dinh_danh",
            )
            ->selectRaw(
                "COUNT(CASE WHEN (dn_trang_thais.ma IS NULL OR dn_trang_thais.ma NOT IN ({$dissolvedList})) AND doanh_nghieps.da_cap_nhat_dinh_danh = 1 THEN 1 END) as da_dinh_danh",
            )
            ->first();

        $soLuongCapGcn = (int) ($row->so_luong_cap_gcn ?? 0);
        $donViGiaiThe = (int) ($row->don_vi_giai_the ?? 0);
        $canDinhDanh = (int) ($row->can_dinh_danh ?? 0);
        $daDinhDanh = (int) ($row->da_dinh_danh ?? 0);

        return [
            'soLuongCapGcn' => $soLuongCapGcn,
            'donViGiaiThe' => $donViGiaiThe,
            'canDinhDanh' => $canDinhDanh,
            'daDinhDanh' => $daDinhDanh,
            'chuaDinhDanh' => max(0, $canDinhDanh - $daDinhDanh),
        ];
    }

    /**
     * @param  Builder<\App\Models\DoanhNghiep>  $query
     * @param  'doanh_nghiep'|'htx'  $entity
     */
    private function applyEntityFilter(Builder $query, string $entity): void
    {
        $htxSql = "(
            LOWER(TRIM(COALESCE(doanh_nghieps.loai_hinh_dn, ''))) LIKE '%htx%'
            OR LOWER(TRIM(COALESCE(doanh_nghieps.loai_hinh_dn, ''))) LIKE '%hợp tác xã%'
            OR LOWER(TRIM(COALESCE(doanh_nghieps.loai_hinh_dn, ''))) LIKE '%liên hiệp%'
        )";

        if ($entity === 'htx') {
            $query->whereRaw($htxSql);

            return;
        }

        $query->whereRaw("NOT {$htxSql}");
    }

    /**
     * @param  Builder<\App\Models\DoanhNghiep>  $query
     */
    private function applyNgayCapRange(Builder $query, ?Carbon $from, ?Carbon $to): void
    {
        $parsedDateSql = $this->ngayCapParsedSql();

        $query->whereRaw("{$parsedDateSql} IS NOT NULL");

        if ($from !== null) {
            $query->whereRaw("{$parsedDateSql} >= ?", [$from->toDateString()]);
        }

        if ($to !== null) {
            $query->whereRaw("{$parsedDateSql} <= ?", [$to->toDateString()]);
        }
    }

    private function ngayCapParsedSql(): string
    {
        $driver = DB::connection()->getDriverName();
        $column = 'TRIM(doanh_nghieps.ngay_cap)';

        if ($driver === 'sqlite') {
            return "COALESCE(
                date({$column}),
                date({$column}, 'start of day'),
                CASE
                    WHEN {$column} GLOB '[0-9][0-9]/[0-9][0-9]/[0-9][0-9][0-9][0-9]'
                        THEN date(substr({$column}, 7, 4) || '-' || substr({$column}, 4, 2) || '-' || substr({$column}, 1, 2))
                    WHEN {$column} GLOB '[0-9][0-9]-[0-9][0-9]-[0-9][0-9][0-9][0-9]'
                        THEN date(substr({$column}, 7, 4) || '-' || substr({$column}, 4, 2) || '-' || substr({$column}, 1, 2))
                    ELSE NULL
                END
            )";
        }

        return "COALESCE(
            STR_TO_DATE({$column}, '%Y-%m-%d'),
            STR_TO_DATE({$column}, '%d/%m/%Y'),
            STR_TO_DATE({$column}, '%d-%m-%Y')
        )";
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (!$value || trim($value) === '') {
            return null;
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
