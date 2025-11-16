<?php

namespace Database\Seeders;

use App\Models\Evaluation;
use App\Models\Rubric;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Seeder;

class EvaluationSeeder extends Seeder
{
    public function run(): void
    {
        Evaluation::query()->delete();

        $submissionIds = Submission::pluck('id')->all();
        $userIds = User::pluck('id')->all();
        $rubricIds = Rubric::pluck('id')->all();

        if (empty($submissionIds) || empty($userIds) || empty($rubricIds)) {
            return;
        }

        $faker = fake();
        $records = [];

        for ($i = 0; $i < 50; $i++) {
            $user = $faker->randomElement($userIds);
            $evaluator = $faker->randomElement($userIds);

            $records[] = [
                'submission_id' => $faker->randomElement($submissionIds),
                'user_id' => $user,
                'evaluator_id' => $evaluator,
                'rubric_id' => $faker->randomElement($rubricIds),
                'grade' => $faker->optional()->randomFloat(2, 60, 100),
                'comments' => $faker->sentence(),
                'evaluation_date' => $faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d H:i:s'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Evaluation::insert($records);
    }
}
