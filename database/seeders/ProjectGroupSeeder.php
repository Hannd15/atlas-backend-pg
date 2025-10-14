<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectGroup;
use Illuminate\Database\Seeder;

class ProjectGroupSeeder extends Seeder
{
    public function run(): void
    {
        ProjectGroup::query()->delete();

        $projectIds = Project::pluck('id')->all();
        if (empty($projectIds)) {
            return;
        }

        $faker = fake();
        $records = [];

        for ($i = 0; $i < 10; $i++) {
            $records[] = [
                'project_id' => $faker->randomElement($projectIds),
                'name' => 'Group ' . strtoupper($faker->unique()->bothify('##??')),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        ProjectGroup::insert($records);
    }
}
