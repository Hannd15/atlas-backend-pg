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

        $stateIds = AcademicPeriodState::pluck('id')->all();
        if (empty($stateIds)) {
            $stateIds = [AcademicPeriodState::activeId()];
        }

        for ($i = 0; $i < 50; $i++) {
            if ($i < 10) {
                $start = $faker->dateTimeBetween('-2 months', 'now');
                $end = $faker->dateTimeBetween('now', '+6 months');
                $stateId = AcademicPeriodState::activeId();
            } else {
                $start = $faker->dateTimeBetween('-3 years', '+1 year');
                $end = (clone $start)->modify('+'.rand(3, 9).' months');
                $stateId = $faker->randomElement($stateIds);
            }

            $periods[] = [
                'name' => 'Periodo Académico '.Str::upper($faker->unique()->bothify('??-####')),
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
                'state_id' => $stateId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        AcademicPeriod::insert($periods);
    }
}
