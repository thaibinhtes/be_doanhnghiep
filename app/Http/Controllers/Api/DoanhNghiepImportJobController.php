<?php

namespace App\Http\Controllers\Api;

use App\Models\DoanhNghiepImportJob;
use App\Models\DoanhNghiepImportJobRow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoanhNghiepImportJobController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', $request->input('perPage', 15)), 1), 50);

        $jobs = DoanhNghiepImportJob::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $jobs->getCollection()->transform(fn (DoanhNghiepImportJob $job) => $this->transformJob($job));

        return $this->paginated($jobs);
    }

    public function show(DoanhNghiepImportJob $importJob): JsonResponse
    {
        if ($importJob->user_id !== request()->user()->id) {
            return $this->error('Không có quyền xem job import này.', 403);
        }

        return $this->success($this->transformJob($importJob, true));
    }

    public function rows(DoanhNghiepImportJob $importJob, Request $request): JsonResponse
    {
        if ($importJob->user_id !== $request->user()->id) {
            return $this->error('Không có quyền xem job import này.', 403);
        }

        $perPage = min(max((int) $request->input('per_page', $request->input('perPage', 50)), 1), 100);
        $status = $request->input('status');

        $query = DoanhNghiepImportJobRow::query()
            ->where('import_job_id', $importJob->id)
            ->orderBy('row_number');

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $rows = $query->paginate($perPage);
        $rows->getCollection()->transform(fn (DoanhNghiepImportJobRow $row) => $this->transformRow($row));

        return $this->paginated($rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformJob(DoanhNghiepImportJob $job, bool $withSummary = false): array
    {
        $result = $job->result ?? [];
        $duplicates = $result['duplicates'] ?? ($result['updated'] ?? 0);

        $data = [
            'id' => $job->id,
            'status' => $job->status,
            'type' => $job->type,
            'originalFilename' => $job->original_filename,
            'result' => $job->result,
            'summary' => [
                'imported' => (int) ($result['imported'] ?? 0),
                'duplicates' => (int) $duplicates,
                'failed' => (int) ($result['failed'] ?? 0),
            ],
            'errorMessage' => $job->error_message,
            'startedAt' => $job->started_at?->toIso8601String(),
            'finishedAt' => $job->finished_at?->toIso8601String(),
            'createdAt' => $job->created_at?->toIso8601String(),
        ];

        if ($withSummary && $job->relationLoaded('rows') === false) {
            $counts = DoanhNghiepImportJobRow::query()
                ->where('import_job_id', $job->id)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $data['rowCounts'] = [
                'success' => (int) ($counts[DoanhNghiepImportJobRow::STATUS_SUCCESS] ?? 0),
                'duplicate' => (int) ($counts[DoanhNghiepImportJobRow::STATUS_DUPLICATE] ?? 0),
                'failed' => (int) ($counts[DoanhNghiepImportJobRow::STATUS_FAILED] ?? 0),
            ];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function transformRow(DoanhNghiepImportJobRow $row): array
    {
        return [
            'id' => $row->id,
            'rowNumber' => $row->row_number,
            'status' => $row->status,
            'maSoDoanhNghiep' => $row->ma_so_doanh_nghiep,
            'tenDoanhNghiep' => $row->ten_doanh_nghiep,
            'doanhNghiepId' => $row->doanh_nghiep_id,
            'message' => $row->message,
            'createdAt' => $row->created_at?->toIso8601String(),
        ];
    }
}
