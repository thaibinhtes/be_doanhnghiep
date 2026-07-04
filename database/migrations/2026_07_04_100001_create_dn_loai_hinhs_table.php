<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dn_loai_hinhs', function (Blueprint $table) {
            $table->id();
            $table->string('ma', 50)->unique();
            $table->string('ten');
            $table->unsignedSmallInteger('thu_tu')->default(0);
            $table->boolean('mac_dinh')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('mo_ta')->nullable();
            $table->timestamps();
        });

        $now = now();
        $rows = [
            ['ma' => 'cong_ty_tnhh', 'ten' => 'Công ty TNHH', 'thu_tu' => 1, 'mac_dinh' => true],
            ['ma' => 'cong_ty_co_phan', 'ten' => 'Công ty Cổ phần', 'thu_tu' => 2, 'mac_dinh' => false],
            ['ma' => 'doanh_nghiep_tu_nhan', 'ten' => 'Doanh nghiệp tư nhân', 'thu_tu' => 3, 'mac_dinh' => false],
            ['ma' => 'hop_danh', 'ten' => 'Hợp danh', 'thu_tu' => 4, 'mac_dinh' => false],
        ];

        foreach ($rows as $row) {
            DB::table('dn_loai_hinhs')->insert(array_merge($row, [
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dn_loai_hinhs');
    }
};
