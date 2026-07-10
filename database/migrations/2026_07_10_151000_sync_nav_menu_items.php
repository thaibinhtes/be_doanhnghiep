<?php

use App\Support\NavMenuService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(NavMenuService::class)->syncFromRegistry();
    }

    public function down(): void
    {
        // Non-destructive.
    }
};
