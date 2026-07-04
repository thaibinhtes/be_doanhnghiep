<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('danh_muc_nganh_nghes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('danh_muc_nganh_nghes')
                ->nullOnDelete();
            $table->unsignedTinyInteger('cap');
            $table->string('ma', 20);
            $table->string('ten');
            $table->unsignedSmallInteger('thu_tu')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['parent_id', 'ma']);
            $table->index(['cap', 'is_active']);
        });

        $now = now();
        $rows = [
            ['cap' => 1, 'ma' => 'A', 'ten' => 'NÔNG NGHIỆP, LÂM NGHIỆP VÀ THỦY SẢN', 'thu_tu' => 1],
            ['cap' => 2, 'ma' => '01', 'ten' => 'Nông nghiệp và hoạt động dịch vụ có liên quan', 'thu_tu' => 1, 'parent_ma' => 'A'],
            ['cap' => 3, 'ma' => '011', 'ten' => 'Trồng cây hàng năm', 'thu_tu' => 1, 'parent_ma' => '01'],
            ['cap' => 4, 'ma' => '0111', 'ten' => 'Trồng lúa', 'thu_tu' => 1, 'parent_ma' => '011'],
            ['cap' => 5, 'ma' => '01110', 'ten' => 'Trồng lúa', 'thu_tu' => 1, 'parent_ma' => '0111'],
            ['cap' => 4, 'ma' => '0112', 'ten' => 'Trồng ngô và cây lương thực có hạt khác', 'thu_tu' => 2, 'parent_ma' => '011'],
            ['cap' => 5, 'ma' => '01120', 'ten' => 'Trồng ngô và cây lương thực có hạt khác', 'thu_tu' => 1, 'parent_ma' => '0112'],
            ['cap' => 4, 'ma' => '0118', 'ten' => 'Trồng rau, đậu các loại và trồng hoa', 'thu_tu' => 3, 'parent_ma' => '011'],
            ['cap' => 5, 'ma' => '01181', 'ten' => 'Trồng rau các loại', 'thu_tu' => 1, 'parent_ma' => '0118'],
            ['cap' => 5, 'ma' => '01182', 'ten' => 'Trồng đậu các loại', 'thu_tu' => 2, 'parent_ma' => '0118'],
            ['cap' => 5, 'ma' => '01183', 'ten' => 'Trồng hoa hàng năm', 'thu_tu' => 3, 'parent_ma' => '0118'],
            ['cap' => 3, 'ma' => '012', 'ten' => 'Trồng cây lâu năm', 'thu_tu' => 2, 'parent_ma' => '01'],
            ['cap' => 4, 'ma' => '0121', 'ten' => 'Trồng cây ăn quả', 'thu_tu' => 1, 'parent_ma' => '012'],
            ['cap' => 5, 'ma' => '01211', 'ten' => 'Trồng nho', 'thu_tu' => 1, 'parent_ma' => '0121'],
            ['cap' => 5, 'ma' => '01212', 'ten' => 'Trồng cây ăn quả vùng nhiệt đới và cận nhiệt đới', 'thu_tu' => 2, 'parent_ma' => '0121'],
            ['cap' => 4, 'ma' => '0122', 'ten' => 'Trồng cây lấy quả chứa dầu', 'thu_tu' => 2, 'parent_ma' => '012'],
            ['cap' => 5, 'ma' => '01220', 'ten' => 'Trồng cây lấy quả chứa dầu', 'thu_tu' => 1, 'parent_ma' => '0122'],
        ];

        $idByMa = [];

        foreach ($rows as $row) {
            $parentId = null;
            if (isset($row['parent_ma'])) {
                $parentId = $idByMa[$row['parent_ma']] ?? null;
            }

            $id = DB::table('danh_muc_nganh_nghes')->insertGetId([
                'parent_id' => $parentId,
                'cap' => $row['cap'],
                'ma' => $row['ma'],
                'ten' => $row['ten'],
                'thu_tu' => $row['thu_tu'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $idByMa[$row['ma']] = $id;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('danh_muc_nganh_nghes');
    }
};
