<?php

namespace Database\Seeders;

use App\Models\Rubric;
use Illuminate\Database\Seeder;

class RubricSeeder extends Seeder
{
    public function run(): void
    {
        Rubric::query()->delete();

        $faker = fake();
        $records = [];

        for ($i = 0; $i < 50; $i++) {
            $min = $faker->numberBetween(0, 50);
            $max = $faker->numberBetween($min + 10, $min + 40);

            $records[] = [
                'name' => 'Rubric '.strtoupper($faker->unique()->bothify('??-##')),
                'description' => $faker->sentence(12),
                'min_value' => $min,
                'max_value' => $max,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Rubric::insert($records);
    }
}
