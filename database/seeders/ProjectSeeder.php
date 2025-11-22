<?php

namespace Database\Seeders;

use App\Models\Phase;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Proposal;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        Project::query()->delete();

        $proposalIds = Proposal::pluck('id')->all();
        $phaseIds = Phase::pluck('id')->all();
        $statusIds = ProjectStatus::pluck('id')->all();

        if (empty($proposalIds) || empty($phaseIds) || empty($statusIds)) {
            return;
        }

        $faker = fake();
        $records = [];

        for ($i = 0; $i < 50; $i++) {
            $records[] = [
                'proposal_id' => $faker->randomElement($proposalIds),
                'phase_id' => $faker->randomElement($phaseIds),
                'title' => ucfirst($faker->unique()->sentence(4)),
                'description' => $faker->paragraph(),
                'status_id' => $faker->randomElement($statusIds),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Project::insert($records);
    }
}
