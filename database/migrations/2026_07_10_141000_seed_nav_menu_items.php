<?php

use App\Support\NavMenuService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // item_key is added in 2026_07_10_150000; sync again in 151000 after that.
        if (! Schema::hasColumn('nav_menu_items', 'item_key')) {
            return;
        }

        app(NavMenuService::class)->syncFromRegistry();
    }

    public function down(): void
    {
        // Keep menu data on rollback.
    }
};
