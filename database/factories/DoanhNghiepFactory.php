<?php

namespace Database\Factories;

use App\Models\DanhMucNganhNghe;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DoanhNghiep>
 */
class DoanhNghiepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $industryCodes = DanhMucNganhNghe::query()->inRandomOrder()->limit(20)->pluck('ma')->all();
        $mainCode = $industryCodes !== [] ? $this->faker->randomElement($industryCodes) : null;
        $otherCodes = $industryCodes !== []
            ? $this->faker->randomElements($industryCodes, min(2, count($industryCodes)))
            : [];

        return [
            'tt' => $this->faker->numberBetween(1, 1000),
            'ma_so_doanh_nghiep' => $this->faker->unique()->numerify('##########'),
            'ten_doanh_nghiep' => $this->faker->company(),
            'dia_chi' => $this->faker->address(),
            'quan_huyen' => $this->faker->randomElement([
                'Quận 1', 'Quận 2', 'Quận 3', 'Quận Bình Thạnh',
                'Quận Gò Vấp', 'Quận Tân Bình', 'Quận Phú Nhuận',
                'Thành phố Long Xuyên', 'Quận Hải Châu',
            ]),
            'phuong_xa' => $this->faker->randomElement([
                'Phường Bến Nghé', 'Phường Thảo Điền', 'Phường Tân Định',
                'Phường Mỹ Hòa', 'Phường Hải Châu 1', 'Phường Thạch Thang',
            ]),
            'von_dieu_le' => $this->faker->randomElement([
                '1000000000', '5000000000', '10000000000', '20000000000',
                '3000000000', '8000000000',
            ]),
            'trang_thai' => $this->faker->randomElement([
                'Đang hoạt động', 'Tạm ngưng', 'Giải thể',
            ]),
            'dien_thoai' => $this->faker->phoneNumber(),
            'chu_so_huu_id' => null,
            'nguoi_dai_dien_id' => null,
            'nganh_nghe_kd_chinh' => $mainCode,
            'nganh_nghe_kd' => array_values(array_unique($otherCodes)),
            'ngay_cap' => $this->faker->date('d/m/Y', '-5 years'),
            'ngay_dang_ky_thay_doi' => $this->faker->optional()->date('d/m/Y', '-1 year'),
            'loai_hinh_dn' => $this->faker->randomElement([
                'Công ty TNHH', 'Công ty Cổ phần', 'Doanh nghiệp tư nhân',
                'Hợp danh', 'Công ty Hợp danh',
            ]),
            'so_luong_lao_dong' => $this->faker->numberBetween(5, 500),
            'loai_dn' => $this->faker->randomElement(['TN', 'CP', 'DNTN', 'HD']),
        ];
    }
}
