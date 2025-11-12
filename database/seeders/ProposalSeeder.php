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

        if (empty($users)) {
            return;
        }

        $this->seedTypes();
        $this->seedStatuses();

        $faker = fake();
        $records = [];

        $typeIds = ProposalType::pluck('id', 'code')->all();
        $statusIds = ProposalStatus::pluck('id', 'code')->all();

        for ($i = 0; $i < 10; $i++) {
            $proposer = $faker->randomElement($users);
            $preferred = $faker->randomElement($users);

            $typeCode = $faker->randomElement(['made_by_student', 'made_by_teacher']);
            $statusCode = $faker->randomElement(['pending', 'approved', 'rejected']);

            $records[] = [
                'title' => ucfirst($faker->unique()->sentence(3)),
                'description' => $faker->paragraph(),
                'proposal_status_id' => $statusIds[$statusCode] ?? $statusIds['pending'] ?? null,
                'proposal_type_id' => $typeIds[$typeCode] ?? $typeIds['made_by_student'] ?? null,
                'proposer_id' => $proposer,
                'preferred_director_id' => $preferred,
                'thematic_line_id' => ! empty($lines) ? $faker->optional()->randomElement($lines) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Proposal::insert($records);
    }

    protected function seedTypes(): void
    {
        if (ProposalType::count() > 0) {
            return;
        }

        ProposalType::insert([
            ['code' => 'made_by_student', 'name' => 'Made by student', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'made_by_teacher', 'name' => 'Made by teacher', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function seedStatuses(): void
    {
        if (ProposalStatus::count() > 0) {
            return;
        }

        ProposalStatus::insert([
            ['code' => 'pending', 'name' => 'Pending', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'approved', 'name' => 'Approved', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'rejected', 'name' => 'Rejected', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
