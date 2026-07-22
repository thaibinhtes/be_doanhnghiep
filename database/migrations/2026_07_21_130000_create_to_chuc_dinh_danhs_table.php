<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('to_chuc_dinh_danhs', function (Blueprint $table) {
            $table->id();
            $table->string('loai_to_chuc', 20); // doanh_nghiep | hop_tac_xa
            $table->string('ma_so', 50);
            $table->string('ten_to_chuc')->nullable();
            $table->foreignId('doanh_nghiep_id')->nullable()->constrained('doanh_nghieps')->nullOnDelete();
            $table->foreignId('hop_tac_xa_id')->nullable()->constrained('hop_tac_xas')->nullOnDelete();
            $table->dateTime('thoi_gian_dinh_danh');
            $table->boolean('da_dinh_danh')->default(true);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nguon', 30)->nullable();
            $table->text('ghi_chu')->nullable();
            $table->timestamps();

            $table->unique(['loai_to_chuc', 'ma_so'], 'to_chuc_dinh_danhs_loai_ma_unique');
            $table->index(['loai_to_chuc', 'da_dinh_danh']);
            $table->index('thoi_gian_dinh_danh');
            $table->index('doanh_nghiep_id');
            $table->index('hop_tac_xa_id');
        });

        Schema::table('hop_tac_xas', function (Blueprint $table) {
            $table->boolean('da_cap_nhat_dinh_danh')->default(false)->after('ghi_chu');
        });

        $this->backfillDoanhNghiepIdentities();
    }

    public function down(): void
    {
        Schema::table('hop_tac_xas', function (Blueprint $table) {
            $table->dropColumn('da_cap_nhat_dinh_danh');
        });

        Schema::dropIfExists('to_chuc_dinh_danhs');
    }

    private function backfillDoanhNghiepIdentities(): void
    {
        if (! Schema::hasTable('doanh_nghieps')) {
            return;
        }

        $now = now()->toDateTimeString();

        DB::table('doanh_nghieps')
            ->where('da_cap_nhat_dinh_danh', true)
            ->whereNotNull('ma_so_doanh_nghiep')
            ->where('ma_so_doanh_nghiep', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($now) {
                $inserts = [];

                foreach ($rows as $dn) {
                    $maSo = trim((string) $dn->ma_so_doanh_nghiep);
                    if ($maSo === '') {
                        continue;
                    }

                    $thoiGian = null;
                    if (Schema::hasTable('dn_dinh_danh_lich_sus')) {
                        $thoiGian = DB::table('dn_dinh_danh_lich_sus')
                            ->where('doanh_nghiep_id', $dn->id)
                            ->where('hanh_dong', 'dang_ky')
                            ->where('gia_tri_moi', true)
                            ->orderByDesc('created_at')
                            ->value('created_at');
                    }

                    $inserts[] = [
                        'loai_to_chuc' => 'doanh_nghiep',
                        'ma_so' => $maSo,
                        'ten_to_chuc' => $dn->ten_doanh_nghiep,
                        'doanh_nghiep_id' => $dn->id,
                        'hop_tac_xa_id' => null,
                        'thoi_gian_dinh_danh' => $thoiGian ?? $dn->updated_at ?? $now,
                        'da_dinh_danh' => true,
                        'user_id' => null,
                        'nguon' => 'backfill',
                        'ghi_chu' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($inserts === []) {
                    return;
                }

                DB::table('to_chuc_dinh_danhs')->upsert(
                    $inserts,
                    ['loai_to_chuc', 'ma_so'],
                    ['ten_to_chuc', 'doanh_nghiep_id', 'thoi_gian_dinh_danh', 'da_dinh_danh', 'nguon', 'updated_at'],
                );
            });
    }
};
