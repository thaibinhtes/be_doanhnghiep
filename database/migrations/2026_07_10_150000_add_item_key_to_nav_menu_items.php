<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nav_menu_items', function (Blueprint $table) {
            if (!Schema::hasColumn('nav_menu_items', 'item_key')) {
                $table->string('item_key', 120)->nullable()->unique()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nav_menu_items', function (Blueprint $table) {
            if (Schema::hasColumn('nav_menu_items', 'item_key')) {
                $table->dropUnique(['item_key']);
                $table->dropColumn('item_key');
            }
        });
    }
};
