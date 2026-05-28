<?php

namespace Database\Seeders;

use App\Models\DoanhNghiep;
use App\Models\Member;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $members = Member::factory(20)->create();

        $companies = DoanhNghiep::factory(10)->make()->each(function ($company) use ($members) {
            $owner = $members->random();
            $rep = $members->random();
            $company->chu_so_huu_id = $owner->id;
            $company->chu_so_huu_ten = $owner->full_name;
            $company->nguoi_dai_dien_id = $rep->id;
            $company->nguoi_dai_dien_ten = $rep->full_name;
            $company->ngay_sinh_nguoi_dai_dien = $rep->birthday;
            $company->save();
        });

        $positions = [
            'Giám đốc', 'Phó giám đốc', 'Kế toán trưởng',
            'Trưởng phòng', 'Nhân viên', 'Chuyên viên',
        ];
        $amounts = [100000000, 200000000, 500000000, 1000000000];

        $companies->each(function ($company) use ($members, $positions, $amounts) {
            $selectedMembers = $members->random(rand(1, 5));
            foreach ($selectedMembers as $member) {
                $company->members()->attach($member->id, [
                    'date_join' => fake()->date('d/m/Y'),
                    'position' => fake()->randomElement($positions),
                    'investment_amount' => fake()->randomElement($amounts),
                ]);
            }
        });
    }
}
