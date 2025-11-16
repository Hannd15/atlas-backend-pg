<?php

namespace Database\Seeders;

use App\Models\ProposalType;
use Illuminate\Database\Seeder;

class ProposalTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ProposalType::query()->delete();

        $faker = fake();
        $records = [
            ['code' => 'made_by_student', 'name' => 'Made by student', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'made_by_teacher', 'name' => 'Made by teacher', 'created_at' => now(), 'updated_at' => now()],
        ];

        for ($i = 1; count($records) < 50; $i++) {
            $records[] = [
                'code' => 'type_'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'name' => ucfirst($faker->unique()->words(3, true)),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        ProposalType::insert($records);
    }
}
