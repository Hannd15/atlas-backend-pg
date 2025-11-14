<?php

namespace Database\Factories;

use App\Models\AcademicPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Phase>
 */
class PhaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $period = AcademicPeriod::factory()->create();
        $startDate = $this->faker->dateTimeBetween($period->start_date, $period->end_date);

        return [
            'period_id' => $period->id,
            'name' => $this->faker->word(),
            'start_date' => $startDate,
            'end_date' => $this->faker->dateTimeBetween($startDate, $period->end_date),
        ];
    }
}
