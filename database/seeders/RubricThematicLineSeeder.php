<?php

namespace Database\Seeders;

use App\Models\Rubric;
use App\Models\RubricThematicLine;
use App\Models\ThematicLine;
use Illuminate\Database\Seeder;

class RubricThematicLineSeeder extends Seeder
{
    public function run(): void
    {
        RubricThematicLine::query()->delete();

        $rubricIds = Rubric::pluck('id')->all();
        $lineIds = ThematicLine::pluck('id')->all();

        if (empty($rubricIds) || empty($lineIds)) {
            return;
        }

        $faker = fake();
        $records = [];
        $used = [];

        $maxCombinations = count($rubricIds) * count($lineIds);
        $target = min(150, max(10, $maxCombinations));

        while (count($records) < $target && count($used) < $maxCombinations) {
            $combo = [
                $faker->randomElement($rubricIds),
                $faker->randomElement($lineIds),
            ];

            $key = implode('-', $combo);
            if (isset($used[$key])) {
                continue;
            }

            $used[$key] = true;

            $records[] = [
                'rubric_id' => $combo[0],
                'thematic_line_id' => $combo[1],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        RubricThematicLine::insert($records);
    }
}
