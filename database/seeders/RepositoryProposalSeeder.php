<?php

namespace Database\Seeders;

use App\Models\Proposal;
use App\Models\RepositoryProposal;
use Illuminate\Database\Seeder;

class RepositoryProposalSeeder extends Seeder
{
    public function run(): void
    {
        RepositoryProposal::query()->delete();

        $proposalIds = Proposal::pluck('id')->all();
        if (empty($proposalIds)) {
            return;
        }

        $faker = fake();
        $records = [];

        for ($i = 0; $i < 10; $i++) {
            $records[] = [
                'proposal_id' => $faker->optional()->randomElement($proposalIds),
                'title' => 'Repository Proposal ' . strtoupper($faker->unique()->bothify('??##')),
                'description' => $faker->paragraph(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        RepositoryProposal::insert($records);
    }
}
