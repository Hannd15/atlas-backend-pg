<?php

namespace Database\Seeders;

use App\Models\Deliverable;
use App\Models\Project;
use App\Models\Submission;
use Illuminate\Database\Seeder;

class SubmissionSeeder extends Seeder
{
    public function run(): void
    {
        Submission::query()->delete();

        $deliverableIds = Deliverable::pluck('id')->all();
        $projectIds = Project::pluck('id')->all();

        if (empty($deliverableIds) || empty($projectIds)) {
            return;
        }

        $faker = fake();
        $records = [];

        for ($i = 0; $i < 50; $i++) {
            $submittedAt = $faker->dateTimeBetween('-1 month', 'now');

            $records[] = [
                'deliverable_id' => $faker->randomElement($deliverableIds),
                'project_id' => $faker->randomElement($projectIds),
                'submission_date' => $submittedAt->format('Y-m-d H:i:s'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Submission::insert($records);
    }
}
