<?php

namespace Database\Seeders;

use App\Models\Proposal;
use App\Models\ProposalStatus;
use App\Models\ProposalType;
use App\Models\ThematicLine;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProposalSeeder extends Seeder
{
    public function run(): void
    {
        Proposal::query()->delete();

        $users = User::pluck('id')->all();
        $lines = ThematicLine::pluck('id')->all();
        $typeIds = ProposalType::pluck('id')->all();
        $statusIds = ProposalStatus::pluck('id')->all();

        if (empty($users) || empty($typeIds) || empty($statusIds)) {
            return;
        }

        $faker = fake();
        $records = [];
        $userCollection = collect($users);

        for ($i = 0; $i < 50; $i++) {
            $proposer = $faker->randomElement($users);
            $preferred = $faker->randomElement($users);

            if ($preferred === $proposer && $userCollection->count() > 1) {
                $preferred = $userCollection->reject(fn (int $id) => $id === $proposer)->random();
            }

            $records[] = [
                'title' => ucfirst($faker->unique()->sentence(4)),
                'description' => $faker->paragraph(3),
                'proposal_status_id' => $faker->randomElement($statusIds),
                'proposal_type_id' => $faker->randomElement($typeIds),
                'proposer_id' => $proposer,
                'preferred_director_id' => $preferred,
                'thematic_line_id' => ! empty($lines) ? $faker->optional(0.7)->randomElement($lines) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Proposal::insert($records);
    }
}
