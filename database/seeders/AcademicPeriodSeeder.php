<?php

namespace Database\Seeders;

use App\Models\AcademicPeriod;
use App\Models\AcademicPeriodState;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AcademicPeriodSeeder extends Seeder
{
    public function run(): void
    {
        AcademicPeriod::query()->delete();

        $faker = fake();
        $periods = [];

        $defaultStateId = AcademicPeriodState::activeId();

        for ($i = 0; $i < 10; $i++) {
            $start = $faker->dateTimeBetween('-2 years', '+1 year');
            $end = (clone $start)->modify('+'.rand(3, 6).' months');

            $periods[] = [
                'name' => 'Academic Period '.Str::upper($faker->unique()->bothify('??-####')),
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
                'state_id' => $defaultStateId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        AcademicPeriod::insert($periods);
    }
}
