<?php

namespace Database\Seeders;

use App\Models\Deliverable;
use App\Models\Rubric;
use App\Models\RubricDeliverable;
use Illuminate\Database\Seeder;

class RubricDeliverableSeeder extends Seeder
{
    public function run(): void
    {
        RubricDeliverable::query()->delete();

        $deliverableIds = Deliverable::pluck('id')->all();
        $rubricIds = Rubric::pluck('id')->all();

        if (empty($deliverableIds) || empty($rubricIds)) {
            return;
        }

        $faker = fake();
        $records = [];
        $used = [];

        while (count($records) < 10) {
            $deliverable = $faker->randomElement($deliverableIds);
            $rubric = $faker->randomElement($rubricIds);
            $key = $rubric . '-' . $deliverable;

            if (isset($used[$key])) {
                continue;
            }
            $used[$key] = true;

            $records[] = [
                'rubric_id' => $rubric,
                'deliverable_id' => $deliverable,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        RubricDeliverable::insert($records);
    }
}
