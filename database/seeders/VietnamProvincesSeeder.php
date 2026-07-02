<?php

namespace Database\Seeders;

use App\Models\TinhThanh;
use App\Models\XaPhuong;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Data source: https://github.com/thanglequoc/vietnamese-provinces-database (MIT)
 * File: json/vn_only_simplified_json_generated_data_vn_units.json
 */
class VietnamProvincesSeeder extends Seeder
{
    /**
     * Chỉ import tỉnh An Giang (AG) theo yêu cầu.
     * Dataset hiện tại dùng mã "91" cho Tỉnh An Giang.
     *
     * @var array<int, string>
     */
    private const ALLOWED_PROVINCE_CODES = ['91'];

    public function run(): void
    {
        $path = database_path('data/vn_provinces.json');

        if (!is_file($path)) {
            throw new RuntimeException("Missing dataset: {$path}");
        }

        $provinces = json_decode(file_get_contents($path), true);

        if (!is_array($provinces)) {
            throw new RuntimeException('Invalid provinces JSON dataset.');
        }

        DB::transaction(function () use ($provinces) {
            XaPhuong::query()->delete();
            TinhThanh::query()->delete();

            $now = now();
            $provinceRows = [];
            $wardRows = [];

            foreach ($provinces as $province) {
                $provinceCode = (string) ($province['Code'] ?? '');
                $provinceName = trim((string) ($province['FullName'] ?? ''));

                if ($provinceCode === '' || $provinceName === '') {
                    continue;
                }

                if (!in_array($provinceCode, self::ALLOWED_PROVINCE_CODES, true)) {
                    continue;
                }

                $provinceRows[] = [
                    'code' => $provinceCode,
                    'full_name' => $provinceName,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                foreach ($province['Wards'] ?? [] as $ward) {
                    $wardCode = (string) ($ward['Code'] ?? '');
                    $wardName = trim((string) ($ward['FullName'] ?? ''));
                    $wardProvinceCode = (string) ($ward['ProvinceCode'] ?? $provinceCode);

                    if ($wardCode === '' || $wardName === '') {
                        continue;
                    }

                    $wardRows[] = [
                        'code' => $wardCode,
                        'full_name' => $wardName,
                        'tinh_thanh_code' => $wardProvinceCode,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($provinceRows, 100) as $chunk) {
                TinhThanh::query()->insert($chunk);
            }

            foreach (array_chunk($wardRows, 500) as $chunk) {
                XaPhuong::query()->insert($chunk);
            }
        });

        $this->command?->info(sprintf(
            'Seeded %d provinces and %d wards.',
            TinhThanh::query()->count(),
            XaPhuong::query()->count(),
        ));
    }
}
