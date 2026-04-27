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
        // Create 20 members
        $members = Member::factory(20)->create();

        // Create 10 companies with random owners and representatives
        $companies = DoanhNghiep::factory(10)->make()->each(function ($company) use ($members) {
            $owner = $members->random();
            $rep = $members->random();
            $company->chu_so_huu_id = $owner->id;
            $company->nguoi_dai_dien_id = $rep->id;
            $company->save();
        });

        // Attach random members to each company with pivot data
        $companies->each(function ($company) use ($members) {
            $selectedMembers = $members->random(rand(1, 5));
            foreach ($selectedMembers as $member) {
                $company->members()->attach($member->id, [
                    'date_join' => $member->date_join,
                    'position' => $member->position,
                    'investment_amount' => $member->investment_amount,
                ]);
            }
        });
    }
}
