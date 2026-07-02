<?php

namespace Database\Seeders;

use App\Models\DnTrangThai;
use Illuminate\Database\Seeder;

class DnTrangThaiSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            [
                'ma' => 'dang_hoat_dong',
                'ten' => 'Số doanh nghiệp đang hoạt động (STC)',
                'loai' => 'hoat_dong',
                'yeu_cau_ly_do' => false,
                'hien_thi_bao_cao' => true,
                'thu_tu_bao_cao' => 1,
                'mac_dinh' => false,
            ],
            [
                'ma' => 'da_dinh_danh',
                'ten' => 'Đã định danh thành công (Công an)',
                'loai' => 'dinh_danh',
                'yeu_cau_ly_do' => false,
                'hien_thi_bao_cao' => true,
                'thu_tu_bao_cao' => 2,
                'mac_dinh' => false,
            ],
            [
                'ma' => 'chua_dinh_danh',
                'ten' => 'Số DN định danh chưa thành công',
                'loai' => 'dinh_danh',
                'yeu_cau_ly_do' => false,
                'hien_thi_bao_cao' => true,
                'thu_tu_bao_cao' => 3,
                'mac_dinh' => true,
            ],
            [
                'ma' => 'giai_the_hop_nhat',
                'ten' => 'Đang làm thủ tục giải thể, đã bị chia, bị hợp nhất, bị sáp nhập',
                'loai' => 'bao_cao',
                'yeu_cau_ly_do' => true,
                'hien_thi_bao_cao' => true,
                'thu_tu_bao_cao' => 4,
                'mac_dinh' => false,
            ],
            [
                'ma' => 'khong_hoat_dong_dia_chi',
                'ten' => 'Không còn hoạt động kinh doanh tại địa chỉ đã đăng ký',
                'loai' => 'bao_cao',
                'yeu_cau_ly_do' => true,
                'hien_thi_bao_cao' => true,
                'thu_tu_bao_cao' => 5,
                'mac_dinh' => false,
            ],
            [
                'ma' => 'thu_hoi_gcn',
                'ten' => 'Bị thu hồi Giấy chứng nhận đăng ký doanh nghiệp do cưỡng chế về quản lý thuế',
                'loai' => 'bao_cao',
                'yeu_cau_ly_do' => true,
                'hien_thi_bao_cao' => true,
                'thu_tu_bao_cao' => 6,
                'mac_dinh' => false,
            ],
            [
                'ma' => 'tam_ngung',
                'ten' => 'Tạm ngừng kinh doanh',
                'loai' => 'bao_cao',
                'yeu_cau_ly_do' => true,
                'hien_thi_bao_cao' => true,
                'thu_tu_bao_cao' => 7,
                'mac_dinh' => false,
            ],
            [
                'ma' => 'giai_the_pha_san',
                'ten' => 'Đã giải thể, phá sản, chấm dứt tồn tại',
                'loai' => 'bao_cao',
                'yeu_cau_ly_do' => true,
                'hien_thi_bao_cao' => true,
                'thu_tu_bao_cao' => 8,
                'mac_dinh' => false,
            ],
            [
                'ma' => 'canh_bao_vi_pham',
                'ten' => 'Bị cảnh báo vi phạm',
                'loai' => 'bao_cao',
                'yeu_cau_ly_do' => true,
                'hien_thi_bao_cao' => true,
                'thu_tu_bao_cao' => 9,
                'mac_dinh' => false,
            ],
            [
                'ma' => 'khac',
                'ten' => 'Khác',
                'loai' => 'bao_cao',
                'yeu_cau_ly_do' => true,
                'hien_thi_bao_cao' => true,
                'thu_tu_bao_cao' => 10,
                'mac_dinh' => false,
            ],
        ];

        foreach ($statuses as $status) {
            DnTrangThai::updateOrCreate(['ma' => $status['ma']], $status);
        }
    }
}
