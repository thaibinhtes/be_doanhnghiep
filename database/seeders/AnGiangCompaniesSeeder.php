<?php

namespace Database\Seeders;

use App\Models\DnTrangThai;
use App\Models\DoanhNghiep;
use App\Models\Member;
use App\Models\TinhThanh;
use App\Models\XaPhuong;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AnGiangCompaniesSeeder extends Seeder
{
    private const PROVINCE_CODE = '91'; // An Giang
    private const COMPANIES_COUNT = 200;

    public function run(): void
    {
        $province = TinhThanh::query()->find(self::PROVINCE_CODE);
        if (!$province) {
            $this->command?->error('Không tìm thấy tỉnh An Giang (code 91). Hãy chạy VietnamProvincesSeeder trước.');
            return;
        }

        $wards = XaPhuong::query()
            ->where('tinh_thanh_code', self::PROVINCE_CODE)
            ->orderBy('code')
            ->get();

        if ($wards->isEmpty()) {
            $this->command?->error('Không có xã/phường cho tỉnh An Giang.');
            return;
        }

        $status = DnTrangThai::query()->where('ma', 'chua_dinh_danh')->first()
            ?? DnTrangThai::query()->where('is_active', true)->orderBy('id')->first();

        if (!$status) {
            $this->command?->error('Không tìm thấy trạng thái doanh nghiệp. Hãy chạy DnTrangThaiSeeder trước.');
            return;
        }

        DB::transaction(function () use ($province, $wards, $status) {
            $members = Member::factory(120)->create();

            $loaiHinh = [
                'Công ty TNHH',
                'Công ty Cổ phần',
                'Doanh nghiệp tư nhân',
                'Hợp danh',
            ];

            $nganhNghe = [
                'Nông nghiệp công nghệ cao',
                'Chế biến thủy sản',
                'Thương mại dịch vụ',
                'Xây dựng hạ tầng',
                'Vận tải nội địa',
                'Sản xuất vật liệu',
            ];

            $positions = ['Giám đốc', 'Phó giám đốc', 'Kế toán trưởng', 'Trưởng phòng'];
            $amounts = [100000000, 200000000, 500000000, 1000000000];

            for ($i = 1; $i <= self::COMPANIES_COUNT; $i++) {
                $ward = $wards[($i - 1) % $wards->count()];
                $owner = $members->random();
                $rep = $members->random();

                $company = DoanhNghiep::updateOrCreate(
                    ['ma_so_doanh_nghiep' => sprintf('160%07d', $i)],
                    [
                        'tt' => $i,
                        'ten_doanh_nghiep' => sprintf('Doanh nghiệp An Giang %03d', $i),
                        'dia_chi' => sprintf('%d %s, %s', fake()->numberBetween(1, 999), $ward->full_name, $province->full_name),
                        'quan_huyen' => $province->full_name,
                        'phuong_xa' => $ward->full_name,
                        'von_dieu_le' => (string) fake()->randomElement([1000000000, 3000000000, 5000000000, 10000000000]),
                        'trang_thai' => $status->ten,
                        'dn_trang_thai_id' => $status->id,
                        'ly_do_trang_thai' => null,
                        'da_cap_nhat_dinh_danh' => false,
                        'dien_thoai' => fake()->numerify('0296#######'),
                        'chu_so_huu_id' => $owner->id,
                        'chu_so_huu_ten' => $owner->full_name,
                        'nguoi_dai_dien_id' => $rep->id,
                        'nguoi_dai_dien_ten' => $rep->full_name,
                        'ngay_sinh_nguoi_dai_dien' => $rep->birthday,
                        'nganh_nghe_kd_chinh' => fake()->randomElement($nganhNghe),
                        'nganh_nghe_kd' => fake()->randomElement($nganhNghe) . '; ' . fake()->randomElement($nganhNghe),
                        'ngay_cap' => fake()->date('d/m/Y', '-8 years'),
                        'ngay_dang_ky_thay_doi' => fake()->optional(0.6)->date('d/m/Y', '-1 year'),
                        'loai_hinh_dn' => fake()->randomElement($loaiHinh),
                        'so_luong_lao_dong' => fake()->numberBetween(5, 300),
                        'ds_co_dong' => null,
                        'loai_dn' => fake()->randomElement(['TN', 'NN']),
                        'long' => fake()->randomFloat(6, 104.5, 105.7),
                        'lat' => fake()->randomFloat(6, 10.1, 11.0),
                    ]
                );

                $selected = $members->random(fake()->numberBetween(1, 3));
                $sync = [];
                foreach ($selected as $idx => $member) {
                    $sync[$member->id] = [
                        'date_join' => fake()->date('d/m/Y', '-4 years'),
                        'position' => $positions[$idx % count($positions)],
                        'investment_amount' => $amounts[$idx % count($amounts)],
                    ];
                }
                $company->members()->sync($sync);
            }
        });

        $count = DoanhNghiep::query()->where('quan_huyen', 'Tỉnh An Giang')->count();
        $this->command?->info("Đã init dữ liệu doanh nghiệp An Giang: {$count} bản ghi.");
    }
}
