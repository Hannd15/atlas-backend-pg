<?php

namespace Database\Seeders;

use App\Models\Deliverable;
use App\Models\Phase;
use Illuminate\Database\Seeder;

class DeliverableSeeder extends Seeder
{
    public function run(): void
    {
        Deliverable::query()->delete();

        $phaseIds = Phase::pluck('id')->all();
        if (empty($phaseIds)) {
            return;
        }

        $faker = fake();
        $records = [];

        for ($i = 0; $i < 50; $i++) {
            $dueDate = $faker->optional()->dateTimeBetween('now', '+6 months');

            if ($dueDate === null) {
                $dueDate = now()->addDays($faker->numberBetween(1, 180))->startOfMinute();
            }

            $records[] = [
                'phase_id' => $faker->randomElement($phaseIds),
                'name' => 'Deliverable '.strtoupper($faker->unique()->bothify('##')),
                'description' => $faker->sentence(15),
                'due_date' => $dueDate->format('Y-m-d H:i:s'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Deliverable::insert($records);
    }
}
