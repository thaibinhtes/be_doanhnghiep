<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Member>
 */
class MemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cccd' => $this->faker->unique()->numerify('############'),
            'full_name' => $this->faker->name(),
            'birthday' => $this->faker->date('d/m/Y'),
            'gender' => $this->faker->randomElement(['Nam', 'Nữ']),
            'date_join' => $this->faker->date('d/m/Y', 'now'),
            'status' => $this->faker->boolean(80),
            'position' => $this->faker->randomElement([
                'Giám đốc', 'Phó giám đốc', 'Kế toán trưởng',
                'Trưởng phòng', 'Nhân viên', 'Chuyên viên',
                'Thư ký', 'Quản lý', 'Trưởng nhóm',
            ]),
            'investment_amount' => $this->faker->randomElement([
                100000000, 200000000, 500000000, 1000000000,
                2000000000, 300000000, 150000000, 800000000,
            ]),
        ];
    }
}
