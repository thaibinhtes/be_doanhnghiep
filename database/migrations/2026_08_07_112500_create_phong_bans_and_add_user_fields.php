<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('phong_bans')) {
            Schema::create('phong_bans', function (Blueprint $table) {
                $table->id();
                $table->string('ma')->unique();
                $table->string('ten');
                $table->unsignedInteger('thu_tu')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'phong_ban_id')) {
                    $table->foreignId('phong_ban_id')
                        ->nullable()
                        ->after('don_vi_id')
                        ->constrained('phong_bans')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('users', 'chuc_danh')) {
                    $table->string('chuc_danh')->nullable()->after('phong_ban_id');
                }
            });
        }

        $now = now();
        $seeds = [
            ['ma' => 'VP', 'ten' => 'Văn phòng', 'thu_tu' => 1],
            ['ma' => 'TCCB', 'ten' => 'Phòng Tổ chức cán bộ', 'thu_tu' => 2],
            ['ma' => 'KT-HCSN', 'ten' => 'Phòng Kế toán HCSN', 'thu_tu' => 3],
            ['ma' => 'QLNS', 'ten' => 'Phòng Quản lý ngân sách', 'thu_tu' => 4],
            ['ma' => 'QLN-TC', 'ten' => 'Phòng Quản lý nợ và tài chính đối ngoại', 'thu_tu' => 5],
        ];

        foreach ($seeds as $row) {
            $exists = DB::table('phong_bans')->where('ma', $row['ma'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('phong_bans')->insert([
                'ma' => $row['ma'],
                'ten' => $row['ten'],
                'thu_tu' => $row['thu_tu'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'phong_ban_id')) {
                    $table->dropConstrainedForeignId('phong_ban_id');
                }
                if (Schema::hasColumn('users', 'chuc_danh')) {
                    $table->dropColumn('chuc_danh');
                }
            });
        }

        Schema::dropIfExists('phong_bans');
    }
};
