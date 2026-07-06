<?php

use App\Support\DemoDataClearService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('demo:clear {--preview : Chỉ hiển thị số bản ghi sẽ xóa} {--force : Bỏ qua xác nhận}', function () {
    $counts = DemoDataClearService::preview();
    $total = array_sum($counts);

    if ($this->option('preview') || (!$this->option('force') && !$this->input->isInteractive())) {
        $this->line('Bảng sẽ xóa:');
        foreach ($counts as $table => $count) {
            $this->line(sprintf('  - %-32s %s', $table, number_format($count, 0, ',', '.')));
        }
        $this->line(sprintf('Tổng: %s bản ghi', number_format($total, 0, ',', '.')));

        return self::SUCCESS;
    }

    if (!$this->option('force')) {
        $this->warn('Sẽ xóa dữ liệu demo / nghiệp vụ:');
        foreach ($counts as $table => $count) {
            $this->line(sprintf('  - %-32s %s', $table, number_format($count, 0, ',', '.')));
        }
        $this->line(sprintf('Tổng: %s bản ghi', number_format($total, 0, ',', '.')));

        if (!$this->confirm('Tiếp tục xóa?', false)) {
            $this->info('Đã hủy.');

            return self::SUCCESS;
        }
    }

    $deleted = DemoDataClearService::clear();

    $this->info('Đã xóa:');
    foreach ($deleted as $table => $count) {
        $this->line(sprintf('  - %-32s %s', $table, number_format($count, 0, ',', '.')));
    }

    return self::SUCCESS;
})->purpose('Xóa dữ liệu demo (DN, HTX, thành viên, import jobs)');
