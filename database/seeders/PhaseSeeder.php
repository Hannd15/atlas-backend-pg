<?php

namespace Database\Seeders;

use App\Models\AcademicPeriod;
use App\Models\Phase;
use Illuminate\Database\Seeder;

class PhaseSeeder extends Seeder
{
    public function run(): void
    {
        Phase::query()->delete();

        $periodIds = AcademicPeriod::pluck('id')->all();
        if (empty($periodIds)) {
            return;
        }

        $faker = fake();
        $records = [];

        for ($i = 0; $i < 10; $i++) {
            $start = $faker->dateTimeBetween('-6 months', '+6 months');
            $end = (clone $start)->modify('+' . rand(1, 3) . ' months');

            $records[] = [
                'period_id' => $faker->randomElement($periodIds),
                'name' => 'Phase ' . strtoupper($faker->lexify('??')),
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Phase::insert($records);
    }
}
