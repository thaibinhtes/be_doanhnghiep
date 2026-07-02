<?php

namespace Database\Seeders;

use App\Models\DnTrangThai;
use App\Models\DoanhNghiep;
use App\Models\Member;
use Illuminate\Database\Seeder;

class DemoDoanhNghiepSeeder extends Seeder
{
    public function run(): void
    {
        $statusByMa = DnTrangThai::query()->pluck('id', 'ma');

        $members = collect([
            ['cccd' => '079203001234', 'full_name' => 'Nguyễn Văn An', 'birthday' => '15/03/1985', 'gender' => 'Nam', 'status' => true],
            ['cccd' => '079203005678', 'full_name' => 'Trần Thị Bình', 'birthday' => '22/07/1990', 'gender' => 'Nữ', 'status' => true],
            ['cccd' => '079203009012', 'full_name' => 'Lê Minh Cường', 'birthday' => '08/11/1988', 'gender' => 'Nam', 'status' => true],
            ['cccd' => '079203003456', 'full_name' => 'Phạm Thu Dung', 'birthday' => '30/01/1992', 'gender' => 'Nữ', 'status' => true],
            ['cccd' => '079203007890', 'full_name' => 'Hoàng Văn Em', 'birthday' => '12/09/1987', 'gender' => 'Nam', 'status' => true],
        ])->map(fn (array $row) => Member::updateOrCreate(['cccd' => $row['cccd']], $row));

        $companies = [
            [
                'ma_so_doanh_nghiep' => '0301234567',
                'ten_doanh_nghiep' => 'Công ty TNHH Thương mại Minh An',
                'dia_chi' => '123 Nguyễn Huệ, Quận 1, TP.HCM',
                'quan_huyen' => 'Quận 1',
                'phuong_xa' => 'Phường Bến Nghé',
                'von_dieu_le' => '5000000000',
                'dien_thoai' => '02838234567',
                'nganh_nghe_kd_chinh' => 'Thương mại',
                'nganh_nghe_kd' => 'Thương mại; Dịch vụ',
                'ngay_cap' => '15/06/2018',
                'loai_hinh_dn' => 'Công ty TNHH',
                'so_luong_lao_dong' => 45,
                'loai_dn' => 'TN',
                'status_ma' => 'dang_hoat_dong',
                'ly_do_trang_thai' => null,
                'da_cap_nhat_dinh_danh' => true,
                'lat' => 10.7769,
                'long' => 106.7009,
            ],
            [
                'ma_so_doanh_nghiep' => '0302345678',
                'ten_doanh_nghiep' => 'Công ty Cổ phần Công nghệ TES',
                'dia_chi' => '456 Lê Lợi, Quận 3, TP.HCM',
                'quan_huyen' => 'Quận 3',
                'phuong_xa' => 'Phường Võ Thị Sáu',
                'von_dieu_le' => '10000000000',
                'dien_thoai' => '02839345678',
                'nganh_nghe_kd_chinh' => 'Công nghệ thông tin',
                'nganh_nghe_kd' => 'Công nghệ; Phần mềm',
                'ngay_cap' => '20/01/2020',
                'loai_hinh_dn' => 'Công ty Cổ phần',
                'so_luong_lao_dong' => 120,
                'loai_dn' => 'CP',
                'status_ma' => 'da_dinh_danh',
                'ly_do_trang_thai' => null,
                'da_cap_nhat_dinh_danh' => true,
                'lat' => 10.7860,
                'long' => 106.6881,
            ],
            [
                'ma_so_doanh_nghiep' => '0303456789',
                'ten_doanh_nghiep' => 'Doanh nghiệp tư nhân Xây dựng Phúc Lộc',
                'dia_chi' => '789 Cách Mạng Tháng 8, Quận Bình Thạnh, TP.HCM',
                'quan_huyen' => 'Quận Bình Thạnh',
                'phuong_xa' => 'Phường 25',
                'von_dieu_le' => '3000000000',
                'dien_thoai' => '02838456789',
                'nganh_nghe_kd_chinh' => 'Xây dựng',
                'nganh_nghe_kd' => 'Xây dựng; Bất động sản',
                'ngay_cap' => '05/09/2019',
                'loai_hinh_dn' => 'Doanh nghiệp tư nhân',
                'so_luong_lao_dong' => 30,
                'loai_dn' => 'DNTN',
                'status_ma' => 'chua_dinh_danh',
                'ly_do_trang_thai' => null,
                'da_cap_nhat_dinh_danh' => false,
                'lat' => 10.8106,
                'long' => 106.7091,
            ],
            [
                'ma_so_doanh_nghiep' => '0304567890',
                'ten_doanh_nghiep' => 'Công ty TNHH Vận tải Đông Nam',
                'dia_chi' => '321 Võ Văn Tần, Quận 3, TP.HCM',
                'quan_huyen' => 'Quận 3',
                'phuong_xa' => 'Phường 6',
                'von_dieu_le' => '8000000000',
                'dien_thoai' => '02838567890',
                'nganh_nghe_kd_chinh' => 'Vận tải',
                'nganh_nghe_kd' => 'Vận tải; Logistics',
                'ngay_cap' => '11/03/2017',
                'loai_hinh_dn' => 'Công ty TNHH',
                'so_luong_lao_dong' => 85,
                'loai_dn' => 'TN',
                'status_ma' => 'tam_ngung',
                'ly_do_trang_thai' => 'Tạm ngừng kinh doanh theo quyết định của chủ doanh nghiệp',
                'da_cap_nhat_dinh_danh' => true,
                'lat' => 10.7825,
                'long' => 106.6902,
            ],
            [
                'ma_so_doanh_nghiep' => '0305678901',
                'ten_doanh_nghiep' => 'Công ty Cổ phần Sản xuất Hòa Bình',
                'dia_chi' => '654 Quang Trung, Quận Gò Vấp, TP.HCM',
                'quan_huyen' => 'Quận Gò Vấp',
                'phuong_xa' => 'Phường 12',
                'von_dieu_le' => '20000000000',
                'dien_thoai' => '02838678901',
                'nganh_nghe_kd_chinh' => 'Sản xuất',
                'nganh_nghe_kd' => 'Sản xuất; Thương mại',
                'ngay_cap' => '28/12/2015',
                'loai_hinh_dn' => 'Công ty Cổ phần',
                'so_luong_lao_dong' => 200,
                'loai_dn' => 'CP',
                'status_ma' => 'khong_hoat_dong_dia_chi',
                'ly_do_trang_thai' => 'Không còn hoạt động tại địa chỉ đăng ký, đã chuyển trụ sở',
                'da_cap_nhat_dinh_danh' => true,
                'lat' => 10.8381,
                'long' => 106.6656,
            ],
            [
                'ma_so_doanh_nghiep' => '0306789012',
                'ten_doanh_nghiep' => 'Công ty TNHH Dịch vụ Tổng hợp An Khang',
                'dia_chi' => '987 Trường Chinh, Quận Tân Bình, TP.HCM',
                'quan_huyen' => 'Quận Tân Bình',
                'phuong_xa' => 'Phường 15',
                'von_dieu_le' => '1500000000',
                'dien_thoai' => '02838789012',
                'nganh_nghe_kd_chinh' => 'Dịch vụ',
                'nganh_nghe_kd' => 'Dịch vụ; Tư vấn',
                'ngay_cap' => '17/08/2021',
                'loai_hinh_dn' => 'Công ty TNHH',
                'so_luong_lao_dong' => 15,
                'loai_dn' => 'TN',
                'status_ma' => 'canh_bao_vi_pham',
                'ly_do_trang_thai' => 'Bị cảnh báo vi phạm về kê khai thuế',
                'da_cap_nhat_dinh_danh' => true,
                'lat' => 10.8014,
                'long' => 106.6526,
            ],
        ];

        $savedCompanies = collect();

        foreach ($companies as $index => $row) {
            $statusMa = $row['status_ma'];
            unset($row['status_ma']);

            $status = DnTrangThai::query()->where('ma', $statusMa)->first();
            if (!$status) {
                continue;
            }

            $owner = $members[$index % $members->count()];
            $rep = $members[($index + 1) % $members->count()];

            $company = DoanhNghiep::updateOrCreate(
                ['ma_so_doanh_nghiep' => $row['ma_so_doanh_nghiep']],
                array_merge($row, [
                    'tt' => $index + 1,
                    'dn_trang_thai_id' => $status->id,
                    'trang_thai' => $status->ten,
                    'chu_so_huu_id' => $owner->id,
                    'chu_so_huu_ten' => $owner->full_name,
                    'nguoi_dai_dien_id' => $rep->id,
                    'nguoi_dai_dien_ten' => $rep->full_name,
                    'ngay_sinh_nguoi_dai_dien' => $rep->birthday,
                ])
            );

            $savedCompanies->push($company);
        }

        $positions = ['Giám đốc', 'Phó giám đốc', 'Kế toán trưởng', 'Trưởng phòng', 'Nhân viên'];
        $amounts = [100000000, 200000000, 500000000, 1000000000];

        foreach ($savedCompanies as $index => $company) {
            $pivotMembers = $members->slice($index, 2)->values();
            if ($pivotMembers->isEmpty()) {
                $pivotMembers = $members->take(2);
            }

            $sync = [];
            foreach ($pivotMembers as $pos => $member) {
                $sync[$member->id] = [
                    'date_join' => '01/01/202' . ($index % 3),
                    'position' => $positions[$pos % count($positions)],
                    'investment_amount' => $amounts[$pos % count($amounts)],
                ];
            }

            $company->members()->sync($sync);
        }
    }
}
