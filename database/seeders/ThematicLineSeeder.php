<?php

namespace Database\Seeders;

use App\Models\ThematicLine;
use Illuminate\Database\Seeder;

class ThematicLineSeeder extends Seeder
{
    public function run(): void
    {
        ThematicLine::query()->delete();

        $faker = fake();
        $records = [];

        for ($i = 0; $i < 50; $i++) {
            $records[] = [
                'name' => 'Línea Temática '.$faker->unique()->words(2, true),
                'description' => $faker->sentence(),
                'trl_expected' => strtoupper($faker->bothify('TRL-#?')),
                'abet_criteria' => $faker->sentence(12),
                'min_score' => $faker->numberBetween(60, 90),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        ThematicLine::insert($records);
    }
}
