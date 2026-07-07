<?php

namespace App\Http\Controllers\Api;

use App\Models\TaxImportJob;
use App\Models\TaxImportJobRow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxImportJobController extends ApiController
{
    public function rows(TaxImportJob $importJob, Request $request): JsonResponse
    {
        if ($importJob->user_id !== $request->user()->id) {
            return $this->error('Không có quyền xem job import này.', 403);
        }

        $perPage = min(max((int) $request->input('per_page', $request->input('perPage', 50)), 1), 100);
        $status = $request->input('status');

        $query = TaxImportJobRow::query()
            ->where('import_job_id', $importJob->id)
            ->orderBy('row_number');

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $rows = $query->paginate($perPage);
        $rows->getCollection()->transform(fn (TaxImportJobRow $row) => $this->transformRow($row));

        return $this->paginated($rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformRow(TaxImportJobRow $row): array
    {
        return [
            'id' => $row->id,
            'rowNumber' => $row->row_number,
            'status' => $row->status,
            'maSoDoanhNghiep' => $row->ma_so_doanh_nghiep,
            'tenDoanhNghiep' => $row->ten_doanh_nghiep,
            'taxUnitCode' => $row->tax_unit_code,
            'doanhNghiepId' => $row->doanh_nghiep_id,
            'taxUnitId' => $row->tax_unit_id,
            'message' => $row->message,
            'createdAt' => $row->created_at?->toIso8601String(),
        ];
    }
}
