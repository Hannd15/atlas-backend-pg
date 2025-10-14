<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Proposal;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        Project::query()->delete();

        $proposalIds = Proposal::pluck('id')->all();

        if (empty($proposalIds)) {
            return;
        }

        $faker = fake();
        $records = [];

        for ($i = 0; $i < 10; $i++) {
            $records[] = [
                'proposal_id' => $faker->optional()->randomElement($proposalIds),
                'title' => ucfirst($faker->unique()->sentence(3)),
                'status' => $faker->randomElement(['active', 'on_hold', 'completed']),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Project::insert($records);
    }
}
