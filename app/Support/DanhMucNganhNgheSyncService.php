<?php

namespace App\Support;

use App\Models\DanhMucNganhNghe;
use Illuminate\Support\Facades\DB;

class DanhMucNganhNgheSyncService
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{imported: int, updated: int, failed: int, errors: array<int, array{row: int, message: string}>}
     */
    public function importRows(array $rows): array
    {
        $imported = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];

        usort($rows, static function (array $a, array $b): int {
            return [$a['cap'], $a['thu_tu'], $a['ma']] <=> [$b['cap'], $b['thu_tu'], $b['ma']];
        });

        DB::transaction(function () use ($rows, &$imported, &$updated, &$failed, &$errors) {
            $idByMa = DanhMucNganhNghe::query()
                ->pluck('id', 'ma')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($rows as $index => $row) {
                $rowNumber = (int) ($row['row'] ?? ($index + 2));

                try {
                    $cap = (int) $row['cap'];
                    $ma = trim((string) $row['ma']);
                    $ten = trim((string) $row['ten']);
                    $thuTu = (int) ($row['thu_tu'] ?? 0);
                    $isActive = (bool) ($row['is_active'] ?? true);
                    $parentMa = trim((string) ($row['parent_ma'] ?? ''));

                    if ($ma === '' || $ten === '') {
                        throw new \InvalidArgumentException('Mã và tên ngành không được để trống.');
                    }

                    if ($cap < 1 || $cap > 5) {
                        throw new \InvalidArgumentException('Cấp ngành phải từ 1 đến 5.');
                    }

                    $parentId = null;
                    if ($cap === 1) {
                        if ($parentMa !== '') {
                            throw new \InvalidArgumentException('Cấp 1 không được có mã cha.');
                        }
                    } else {
                        if ($parentMa === '') {
                            throw new \InvalidArgumentException('Thiếu mã danh mục cha.');
                        }

                        $parentId = $idByMa[$parentMa] ?? null;
                        if (!$parentId) {
                            throw new \InvalidArgumentException("Không tìm thấy danh mục cha với mã {$parentMa}.");
                        }

                        $parent = DanhMucNganhNghe::query()->find($parentId);
                        if (!$parent || (int) $parent->cap !== $cap - 1) {
                            throw new \InvalidArgumentException("Danh mục cha {$parentMa} không thuộc cấp " . ($cap - 1) . '.');
                        }
                    }

                    $existing = DanhMucNganhNghe::query()
                        ->where('parent_id', $parentId)
                        ->where('ma', $ma)
                        ->first();

                    if ($existing) {
                        $existing->update([
                            'ten' => $ten,
                            'thu_tu' => $thuTu,
                            'is_active' => $isActive,
                        ]);
                        $idByMa[$ma] = $existing->id;
                        $updated++;
                    } else {
                        $created = DanhMucNganhNghe::query()->create([
                            'parent_id' => $parentId,
                            'cap' => $cap,
                            'ma' => $ma,
                            'ten' => $ten,
                            'thu_tu' => $thuTu,
                            'is_active' => $isActive,
                        ]);
                        $idByMa[$ma] = $created->id;
                        $imported++;
                    }
                } catch (\Throwable $exception) {
                    $failed++;
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => $exception->getMessage(),
                    ];
                }
            }
        });

        return [
            'imported' => $imported,
            'updated' => $updated,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }
}
