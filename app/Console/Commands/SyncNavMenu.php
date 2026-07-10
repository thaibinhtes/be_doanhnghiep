<?php

namespace App\Console\Commands;

use App\Support\NavMenuService;
use Illuminate\Console\Command;

class SyncNavMenu extends Command
{
    protected $signature = 'nav-menu:sync';

    protected $description = 'Đồng bộ menu mặc định (bổ sung mục thiếu, giữ tên và thứ tự đã cấu hình)';

    public function handle(NavMenuService $navMenuService): int
    {
        $navMenuService->syncFromRegistry();
        $this->info('Đã đồng bộ menu. Tổng mục: '.$navMenuService->count());

        return self::SUCCESS;
    }
}
