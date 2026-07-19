<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hanh_chinh_tinh', function (Blueprint $table) {
            $table->id();
            $table->string('ten');
            $table->string('loai', 8); // cu | moi
            $table->string('ma', 32)->nullable();
            $table->timestamps();

            $table->unique(['loai', 'ten']);
            $table->index(['loai', 'ma']);
        });

        Schema::create('hanh_chinh_quan_huyen', function (Blueprint $table) {
            $table->id();
            $table->string('ten');
            $table->string('loai', 8);
            $table->string('ma', 32)->nullable();
            $table->foreignId('tinh_id')->nullable()->constrained('hanh_chinh_tinh')->nullOnDelete();
            $table->timestamps();

            $table->unique(['loai', 'tinh_id', 'ten']);
            $table->index(['loai', 'ma']);
        });

        Schema::create('hanh_chinh_phuong_xa', function (Blueprint $table) {
            $table->id();
            $table->string('ten');
            $table->string('loai', 8);
            $table->string('ma', 32)->nullable();
            $table->foreignId('quan_huyen_id')->nullable()->constrained('hanh_chinh_quan_huyen')->nullOnDelete();
            $table->foreignId('tinh_id')->nullable()->constrained('hanh_chinh_tinh')->nullOnDelete();
            $table->timestamps();

            $table->unique(['loai', 'quan_huyen_id', 'ten']);
            $table->index(['loai', 'ma']);
        });

        $this->seedFromLegacyCatalogs();

        if (Schema::hasTable('doanh_nghieps')) {
            Schema::table('doanh_nghieps', function (Blueprint $table) {
                $table->foreignId('tinh_thanh_cu_id')->nullable()->after('tinh_thanh_cu')
                    ->constrained('hanh_chinh_tinh')->nullOnDelete();
                $table->foreignId('quan_huyen_cu_id')->nullable()->after('quan_huyen_cu')
                    ->constrained('hanh_chinh_quan_huyen')->nullOnDelete();
                $table->foreignId('xa_phuong_cu_id')->nullable()->after('xa_phuong_cu')
                    ->constrained('hanh_chinh_phuong_xa')->nullOnDelete();
                $table->foreignId('quan_huyen_moi_id')->nullable()->after('quan_huyen_moi')
                    ->constrained('hanh_chinh_quan_huyen')->nullOnDelete();
                $table->foreignId('xa_phuong_moi_id')->nullable()->after('xa_phuong_moi')
                    ->constrained('hanh_chinh_phuong_xa')->nullOnDelete();
            });

            $this->backfillCompanyIds();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('doanh_nghieps')) {
            Schema::table('doanh_nghieps', function (Blueprint $table) {
                foreach ([
                    'tinh_thanh_cu_id',
                    'quan_huyen_cu_id',
                    'xa_phuong_cu_id',
                    'quan_huyen_moi_id',
                    'xa_phuong_moi_id',
                ] as $column) {
                    if (Schema::hasColumn('doanh_nghieps', $column)) {
                        $table->dropConstrainedForeignId($column);
                    }
                }
            });
        }

        Schema::dropIfExists('hanh_chinh_phuong_xa');
        Schema::dropIfExists('hanh_chinh_quan_huyen');
        Schema::dropIfExists('hanh_chinh_tinh');
    }

    /**
     * Chuyển dữ liệu danh mục cũ/mới hiện có sang 3 bảng hợp nhất.
     * Cũ: tinh_thanh_cu → tỉnh, quan_huyen_cu → quận huyện, xa_phuong_cu → phường xã.
     * Mới: tinh_thanh (hiển thị "Cấp huyện mới") → quận huyện, xa_phuong → phường xã.
     */
    private function seedFromLegacyCatalogs(): void
    {
        $now = now();

        $legacyProvinceIds = [];
        if (Schema::hasTable('tinh_thanh_cu')) {
            foreach (DB::table('tinh_thanh_cu')->orderBy('code')->get() as $row) {
                $legacyProvinceIds[$row->code] = DB::table('hanh_chinh_tinh')->insertGetId([
                    'ten' => $row->full_name,
                    'loai' => 'cu',
                    'ma' => $row->code,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $legacyDistrictIds = [];
        if (Schema::hasTable('quan_huyen_cu')) {
            foreach (DB::table('quan_huyen_cu')->orderBy('code')->get() as $row) {
                $legacyDistrictIds[$row->code] = DB::table('hanh_chinh_quan_huyen')->insertGetId([
                    'ten' => $row->full_name,
                    'loai' => 'cu',
                    'ma' => $row->code,
                    'tinh_id' => $legacyProvinceIds[$row->tinh_thanh_cu_code] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('xa_phuong_cu')) {
            foreach (DB::table('xa_phuong_cu')->orderBy('code')->get() as $row) {
                DB::table('hanh_chinh_phuong_xa')->insert([
                    'ten' => $row->full_name,
                    'loai' => 'cu',
                    'ma' => $row->code,
                    'quan_huyen_id' => $legacyDistrictIds[$row->quan_huyen_cu_code] ?? null,
                    'tinh_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $newDistrictIds = [];
        if (Schema::hasTable('tinh_thanh')) {
            foreach (DB::table('tinh_thanh')->orderBy('code')->get() as $row) {
                $newDistrictIds[$row->code] = DB::table('hanh_chinh_quan_huyen')->insertGetId([
                    'ten' => $row->full_name,
                    'loai' => 'moi',
                    'ma' => $row->code,
                    'tinh_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('xa_phuong')) {
            foreach (DB::table('xa_phuong')->orderBy('code')->get() as $row) {
                DB::table('hanh_chinh_phuong_xa')->insert([
                    'ten' => $row->full_name,
                    'loai' => 'moi',
                    'ma' => $row->code,
                    'quan_huyen_id' => $newDistrictIds[$row->tinh_thanh_code] ?? null,
                    'tinh_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function backfillCompanyIds(): void
    {
        DB::table('doanh_nghieps')
            ->whereNotNull('tinh_thanh_cu_code')
            ->update([
                'tinh_thanh_cu_id' => DB::raw(
                    "(SELECT id FROM hanh_chinh_tinh WHERE loai = 'cu' AND ma = doanh_nghieps.tinh_thanh_cu_code LIMIT 1)"
                ),
            ]);

        DB::table('doanh_nghieps')
            ->whereNotNull('quan_huyen_cu_code')
            ->update([
                'quan_huyen_cu_id' => DB::raw(
                    "(SELECT id FROM hanh_chinh_quan_huyen WHERE loai = 'cu' AND ma = doanh_nghieps.quan_huyen_cu_code LIMIT 1)"
                ),
            ]);

        DB::table('doanh_nghieps')
            ->whereNotNull('xa_phuong_cu_code')
            ->update([
                'xa_phuong_cu_id' => DB::raw(
                    "(SELECT id FROM hanh_chinh_phuong_xa WHERE loai = 'cu' AND ma = doanh_nghieps.xa_phuong_cu_code LIMIT 1)"
                ),
            ]);

        DB::table('doanh_nghieps')
            ->whereNotNull('tinh_thanh_code')
            ->update([
                'quan_huyen_moi_id' => DB::raw(
                    "(SELECT id FROM hanh_chinh_quan_huyen WHERE loai = 'moi' AND ma = doanh_nghieps.tinh_thanh_code LIMIT 1)"
                ),
            ]);

        DB::table('doanh_nghieps')
            ->whereNotNull('xa_phuong_code')
            ->update([
                'xa_phuong_moi_id' => DB::raw(
                    "(SELECT id FROM hanh_chinh_phuong_xa WHERE loai = 'moi' AND ma = doanh_nghieps.xa_phuong_code LIMIT 1)"
                ),
            ]);
    }
};
