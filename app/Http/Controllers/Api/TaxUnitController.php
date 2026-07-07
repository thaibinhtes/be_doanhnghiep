<?php

namespace App\Http\Controllers\Api;

use App\Jobs\ProcessTaxUnitImportJob;
use App\Http\Resources\TaxUnitResource;
use App\Models\TaxImportJob;
use App\Models\TaxUnit;
use App\Support\TaxExcelColumns;
use App\Support\TaxImportColumnMap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxUnitController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $query = TaxUnit::query()->orderBy('unit_code');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('unit_code', 'like', "%{$search}%")
                    ->orWhere('unit_name', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->query('perPage', $request->query('per_page', 50)), 1), 200);

        return $this->paginated(
            TaxUnitResource::collection($query->paginate($perPage)),
            'Lấy danh sách đơn vị thuế thành công',
        );
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'unitCode' => ['required', 'string', 'max:50', 'unique:tax_units,unit_code'],
            'unitName' => ['required', 'string', 'max:255'],
        ]);

        $item = TaxUnit::query()->create([
            'unit_code' => trim($payload['unitCode']),
            'unit_name' => trim($payload['unitName']),
        ]);

        return $this->success(new TaxUnitResource($item), 'Tạo đơn vị thuế thành công', 201);
    }

    public function update(TaxUnit $taxUnit, Request $request): JsonResponse
    {
        $payload = $request->validate([
            'unitCode' => ['required', 'string', 'max:50', 'unique:tax_units,unit_code,' . $taxUnit->id],
            'unitName' => ['required', 'string', 'max:255'],
        ]);

        $taxUnit->update([
            'unit_code' => trim($payload['unitCode']),
            'unit_name' => trim($payload['unitName']),
        ]);

        return $this->success(new TaxUnitResource($taxUnit->fresh()), 'Cập nhật đơn vị thuế thành công');
    }

    public function destroy(TaxUnit $taxUnit): JsonResponse
    {
        if ($taxUnit->companyTaxManagements()->exists() || $taxUnit->cooperativeTaxManagements()->exists()) {
            return $this->error('Đơn vị thuế đang được sử dụng, không thể xóa.', 422);
        }

        $taxUnit->delete();

        return $this->success(null, 'Xóa đơn vị thuế thành công');
    }

    public function importColumnMap(): JsonResponse
    {
        return $this->success([
            'startRow' => TaxImportColumnMap::DEFAULT_START_ROW,
            'columnMap' => TaxImportColumnMap::TAX_UNIT_COLUMN_MAP,
            'columnLabels' => TaxExcelColumns::taxUnitColumnLabels(),
            'valueExtensions' => [],
        ]);
    }

    public function importExcel(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
            'startRow' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'columnMap' => ['nullable'],
        ]);

        $startRow = $request->has('startRow') ? (int) $request->input('startRow') : TaxImportColumnMap::DEFAULT_START_ROW;
        $columnMap = $this->parseImportColumnMap($request->input('columnMap'));
        $uploadedFile = $request->file('file');
        $storedPath = $uploadedFile->store('imports/pending');

        $importJob = TaxImportJob::query()->create([
            'user_id' => (int) $request->user()->id,
            'status' => TaxImportJob::STATUS_PENDING,
            'type' => TaxImportJob::TYPE_TAX_UNITS,
            'file_path' => $storedPath,
            'original_filename' => $uploadedFile->getClientOriginalName(),
            'start_row' => $startRow,
            'column_map' => $columnMap,
        ]);

        ProcessTaxUnitImportJob::dispatch($importJob->id);

        return $this->success([
            'importJobId' => $importJob->id,
            'status' => $importJob->status,
            'originalFilename' => $importJob->original_filename,
            'entity' => 'tax-unit',
        ], 'Đã đưa file import đơn vị thuế vào hàng đợi.', 202);
    }

    /**
     * @return array<string, list<string>>|null
     */
    private function parseImportColumnMap(mixed $input): ?array
    {
        if ($input === null || $input === '') {
            return null;
        }

        if (is_string($input)) {
            $decoded = json_decode($input, true);

            return is_array($decoded) ? $decoded : null;
        }

        return is_array($input) ? $input : null;
    }
}
