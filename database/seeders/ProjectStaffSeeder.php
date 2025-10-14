<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectPosition;
use App\Models\ProjectStaff;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectStaffSeeder extends Seeder
{
    public function run(): void
    {
        ProjectStaff::query()->delete();

        $projectIds = Project::pluck('id')->all();
        $userIds = User::pluck('id')->all();
        $positionIds = ProjectPosition::pluck('id')->all();

        if (empty($projectIds) || empty($userIds) || empty($positionIds)) {
            return;
        }

        $faker = fake();
        $records = [];
        $used = [];

        while (count($records) < 10) {
            $project = $faker->randomElement($projectIds);
            $user = $faker->randomElement($userIds);
            $position = $faker->randomElement($positionIds);

            $key = implode('-', [$project, $user, $position]);
            if (isset($used[$key])) {
                continue;
            }

            $used[$key] = true;

            $records[] = [
                'project_id' => $project,
                'user_id' => $user,
                'project_position_id' => $position,
                'status' => $faker->randomElement(['active', 'inactive']),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        ProjectStaff::insert($records);
    }
}
