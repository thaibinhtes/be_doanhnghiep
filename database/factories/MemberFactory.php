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
            'status' => $this->faker->boolean(80),
        ];
    }
}
