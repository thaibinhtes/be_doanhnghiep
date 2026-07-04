<?php

use App\Models\DnLoaiHinh;
use App\Models\DoanhNghiep;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $table->foreignId('dn_loai_hinh_id')
                ->nullable()
                ->after('loai_hinh_dn')
                ->constrained('dn_loai_hinhs')
                ->nullOnDelete();
        });

        $typesByName = DnLoaiHinh::query()->get()->keyBy(fn (DnLoaiHinh $type) => mb_strtolower(trim($type->ten)));

        DoanhNghiep::query()
            ->whereNotNull('loai_hinh_dn')
            ->where('loai_hinh_dn', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($companies) use ($typesByName) {
                foreach ($companies as $company) {
                    $name = trim((string) $company->loai_hinh_dn);
                    $key = mb_strtolower($name);

                    $type = $typesByName->get($key);

                    if (!$type) {
                        $ma = Str::slug($name, '_');
                        if ($ma === '') {
                            $ma = 'loai_' . $company->id;
                        }

                        $baseMa = $ma;
                        $suffix = 1;
                        while (DnLoaiHinh::query()->where('ma', $ma)->exists()) {
                            $ma = $baseMa . '_' . $suffix;
                            $suffix++;
                        }

                        $type = DnLoaiHinh::query()->create([
                            'ma' => $ma,
                            'ten' => $name,
                            'thu_tu' => 100 + $typesByName->count(),
                            'mac_dinh' => false,
                            'is_active' => true,
                        ]);

                        $typesByName->put($key, $type);
                    }

                    $company->update([
                        'dn_loai_hinh_id' => $type->id,
                        'loai_hinh_dn' => $type->ten,
                    ]);
                }
            });

        $defaultType = DnLoaiHinh::query()->where('mac_dinh', true)->first();
        if ($defaultType) {
            DoanhNghiep::query()
                ->whereNull('dn_loai_hinh_id')
                ->update([
                    'dn_loai_hinh_id' => $defaultType->id,
                    'loai_hinh_dn' => $defaultType->ten,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('doanh_nghieps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dn_loai_hinh_id');
        });
    }
};
