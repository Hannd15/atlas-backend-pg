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

        for ($i = 0; $i < 50; $i++) {
            Meeting::query()->create([
                'project_id' => $faker->randomElement($projectIds),
                'meeting_date' => $faker->dateTimeBetween('-2 months', '+2 months')->format('Y-m-d'),
                'observations' => $faker->optional()->sentence(),
                'created_by' => $faker->randomElement($userIds),
            ]);
        }
    }
}
