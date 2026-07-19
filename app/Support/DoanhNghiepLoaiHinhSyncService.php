<?php

namespace App\Support;

use App\Models\DnLoaiHinh;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DoanhNghiepLoaiHinhSyncService
{
    /**
     * Tạo/tìm danh mục từ text trên doanh nghiệp và chỉ cập nhật ID liên kết.
     * Text nguồn loai_hinh_dn luôn được giữ nguyên.
     *
     * @return array<string, int>
     */
    public function sync(bool $dryRun = false, ?User $user = null): array
    {
        $typesByName = DnLoaiHinh::query()
            ->get()
            ->keyBy(fn (DnLoaiHinh $type) => $this->normalizeKey($type->ten));
        $usedCodes = DnLoaiHinh::query()->pluck('ma')->flip()->all();
        $nextOrder = ((int) DnLoaiHinh::query()->max('thu_tu')) + 1;

        $result = [
            'scanned' => 0,
            'matched' => 0,
            'createdTypes' => 0,
            'updatedCompanies' => 0,
            'skipped' => 0,
        ];

        DoanhNghiepScopeHelper::query($user)
            ->whereNotNull('loai_hinh_dn')
            ->where('loai_hinh_dn', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($companies) use (
                &$typesByName,
                &$usedCodes,
                &$nextOrder,
                &$result,
                $dryRun,
            ) {
                foreach ($companies as $company) {
                    $result['scanned']++;
                    $name = HanhChinhCodeGenerator::normalizeName((string) $company->loai_hinh_dn);
                    $key = $this->normalizeKey($name);

                    if ($key === '') {
                        $result['skipped']++;

                        continue;
                    }

                    /** @var DnLoaiHinh|null $type */
                    $type = $typesByName->get($key);
                    if ($type) {
                        $result['matched']++;
                    } else {
                        $result['createdTypes']++;
                        $code = $this->uniqueCode($name, $key, $usedCodes);
                        $usedCodes[$code] = true;

                        if ($dryRun) {
                            $type = new DnLoaiHinh([
                                'ma' => $code,
                                'ten' => $name,
                                'thu_tu' => $nextOrder++,
                                'mac_dinh' => false,
                                'is_active' => true,
                            ]);
                            $type->id = -$result['createdTypes'];
                        } else {
                            $type = DnLoaiHinh::query()->create([
                                'ma' => $code,
                                'ten' => $name,
                                'thu_tu' => $nextOrder++,
                                'mac_dinh' => false,
                                'is_active' => true,
                            ]);
                        }

                        $typesByName->put($key, $type);
                    }

                    if ((int) $company->dn_loai_hinh_id === (int) $type->id) {
                        continue;
                    }

                    $result['updatedCompanies']++;
                    if (! $dryRun) {
                        DB::table('doanh_nghieps')
                            ->where('id', $company->id)
                            ->update([
                                'dn_loai_hinh_id' => $type->id,
                                'updated_at' => now(),
                            ]);
                    }
                }
            });

        return $result;
    }

    private function normalizeKey(string $name): string
    {
        return mb_strtolower(HanhChinhCodeGenerator::normalizeName($name));
    }

    /**
     * @param  array<string, mixed>  $usedCodes
     */
    private function uniqueCode(string $name, string $normalizedKey, array $usedCodes): string
    {
        $base = Str::slug($name, '_');
        $base = $base === '' ? 'loai_hinh' : $base;
        $base = mb_substr($base, 0, 40);
        $candidate = $base;

        if (isset($usedCodes[$candidate])) {
            $candidate = mb_substr($base, 0, 31).'_'.substr(md5($normalizedKey), 0, 8);
        }

        $suffix = 2;
        while (isset($usedCodes[$candidate])) {
            $tail = '_'.$suffix++;
            $candidate = mb_substr($base, 0, 50 - strlen($tail)).$tail;
        }

        return $candidate;
    }
}
