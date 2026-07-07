<?php

namespace App\Http\Controllers\Api;

use App\Models\HopTacXaImportJob;
use App\Models\HopTacXaImportJobRow;
use App\Support\ImportJobScopeHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HopTacXaImportJobController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', $request->input('perPage', 15)), 1), 50);

        $jobs = ImportJobScopeHelper::applyScope(
            HopTacXaImportJob::query()
                ->with(['user:id,name', 'donVi:id,ten,ma'])
                ->orderByDesc('created_at'),
            $request->user(),
        )->paginate($perPage);

        $jobs->getCollection()->transform(fn (HopTacXaImportJob $job) => $this->transformJob($job));

        return $this->paginated($jobs);
    }

    public function show(HopTacXaImportJob $importJob): JsonResponse
    {
        if (!ImportJobScopeHelper::userCanAccess(request()->user(), $importJob)) {
            return $this->error('Không có quyền xem job import này.', 403);
        }

        $importJob->load(['user:id,name', 'donVi:id,ten,ma']);

        return $this->success($this->transformJob($importJob, true));
    }

    public function rows(HopTacXaImportJob $importJob, Request $request): JsonResponse
    {
        if (!ImportJobScopeHelper::userCanAccess($request->user(), $importJob)) {
            return $this->error('Không có quyền xem job import này.', 403);
        }

        $perPage = min(max((int) $request->input('per_page', $request->input('perPage', 50)), 1), 100);
        $status = $request->input('status');

        $query = HopTacXaImportJobRow::query()
            ->where('import_job_id', $importJob->id)
            ->orderBy('row_number');

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $rows = $query->paginate($perPage);
        $rows->getCollection()->transform(fn (HopTacXaImportJobRow $row) => $this->transformRow($row));

        return $this->paginated($rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformJob(HopTacXaImportJob $job, bool $withSummary = false): array
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
            'importedBy' => $job->user ? [
                'id' => $job->user->id,
                'name' => $job->user->name,
            ] : null,
            'donVi' => $job->donVi ? [
                'id' => $job->donVi->id,
                'ten' => $job->donVi->ten,
                'ma' => $job->donVi->ma,
            ] : null,
            'startedAt' => $job->started_at?->toIso8601String(),
            'finishedAt' => $job->finished_at?->toIso8601String(),
            'createdAt' => $job->created_at?->toIso8601String(),
        ];

        if ($withSummary && $job->relationLoaded('rows') === false) {
            $counts = HopTacXaImportJobRow::query()
                ->where('import_job_id', $job->id)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $data['rowCounts'] = [
                'success' => (int) ($counts[HopTacXaImportJobRow::STATUS_SUCCESS] ?? 0),
                'duplicate' => (int) ($counts[HopTacXaImportJobRow::STATUS_DUPLICATE] ?? 0),
                'failed' => (int) ($counts[HopTacXaImportJobRow::STATUS_FAILED] ?? 0),
            ];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function transformRow(HopTacXaImportJobRow $row): array
    {
        return [
            'id' => $row->id,
            'rowNumber' => $row->row_number,
            'status' => $row->status,
            'maSoThue' => $row->ma_so_thue,
            'tenHtx' => $row->ten_htx,
            'hopTacXaId' => $row->hop_tac_xa_id,
            'message' => $row->message,
            'createdAt' => $row->created_at?->toIso8601String(),
        ];
    }
}
