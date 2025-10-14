<?php

namespace Database\Seeders;

use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class MeetingSeeder extends Seeder
{
    public function run(): void
    {
        Meeting::query()->delete();

        $projectIds = Project::pluck('id')->all();
        $userIds = User::pluck('id')->all();

        if (empty($projectIds) || empty($userIds)) {
            return;
        }

        $faker = fake();
        $records = [];

        for ($i = 0; $i < 10; $i++) {
            $records[] = [
                'project_id' => $faker->randomElement($projectIds),
                'meeting_date' => $faker->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
                'observations' => $faker->optional()->sentence(),
                'created_by' => $faker->randomElement($userIds),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Meeting::insert($records);
    }
}
