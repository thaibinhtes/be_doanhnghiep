<?php

use App\Support\NavMenuService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(NavMenuService::class)->seedFromRegistry(force: true);
    }

    public function down(): void
    {
        // Keep menu data on rollback.
    }
};
