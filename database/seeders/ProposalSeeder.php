<?php

namespace Database\Seeders;

use App\Models\Proposal;
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

        if (empty($users)) {
            return;
        }

        $faker = fake();
        $records = [];

        for ($i = 0; $i < 10; $i++) {
            $proposer = $faker->randomElement($users);
            $preferred = $faker->randomElement($users);

            $records[] = [
                'title' => ucfirst($faker->unique()->sentence(3)),
                'description' => $faker->paragraph(),
                'status' => $faker->randomElement(['pending', 'approved', 'rejected']),
                'type' => $faker->randomElement(['research', 'development', 'innovation']),
                'proposer_id' => $proposer,
                'preferred_director_id' => $preferred,
                'thematic_line_id' => $faker->optional()->randomElement($lines),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Proposal::insert($records);
    }
}
