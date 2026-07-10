<?php

namespace App\Support;

use App\Models\DnDinhDanhLichSu;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class DinhDanhLichSuReportService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(?User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        $requestedDonViId = $this->resolveDonViFilterId($user, $filters);

        $query = DnDinhDanhLichSu::query()
            ->with(['user:id,name,email', 'doanhNghiep.donVi:id,ma,ten'])
            ->whereHas('doanhNghiep', function (Builder $companyQuery) use ($user, $requestedDonViId) {
                DoanhNghiepScopeHelper::applyScope($companyQuery, $user);

                if ($requestedDonViId === null) {
                    return;
                }

                $scopeDonViIds = DoanhNghiepScopeHelper::resolveDonViFilterIds($user, $requestedDonViId);
                if ($scopeDonViIds === []) {
                    $companyQuery->whereRaw('1 = 0');
                } else {
                    $companyQuery->whereIn('don_vi_id', $scopeDonViIds);
                }
            })
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $term = trim($search);
                if ($term === '') {
                    return;
                }

                $query->where(function (Builder $builder) use ($term) {
                    $builder
                        ->where('ma_so_doanh_nghiep', 'like', "%{$term}%")
                        ->orWhere('ten_doanh_nghiep', 'like', "%{$term}%");
                });
            })
            ->when($filters['nguon'] ?? null, fn (Builder $query, string $nguon) => $query->where('nguon', $nguon))
            ->when($filters['hanhDong'] ?? null, fn (Builder $query, string $hanhDong) => $query->where('hanh_dong', $hanhDong))
            ->when($filters['dateFrom'] ?? null, fn (Builder $query, string $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($filters['dateTo'] ?? null, fn (Builder $query, string $dateTo) => $query->whereDate('created_at', '<=', $dateTo))
            ->latest();

        return $query->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function resolveDonViFilterId(?User $user, array $filters): ?int
    {
        if (!empty($filters['donViId'])) {
            return (int) $filters['donViId'];
        }

        if ($user !== null && !DoanhNghiepScopeHelper::hasUnrestrictedScope($user) && $user->don_vi_id !== null) {
            return (int) $user->don_vi_id;
        }

        return null;
    }
}
