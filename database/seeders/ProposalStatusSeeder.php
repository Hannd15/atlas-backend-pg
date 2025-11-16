<?php

namespace Database\Seeders;

use App\Models\ProposalStatus;
use Illuminate\Database\Seeder;

class ProposalStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ProposalStatus::query()->delete();

        $faker = fake();
        $records = [
            ['code' => 'pending', 'name' => 'Pending', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'approved', 'name' => 'Approved', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'rejected', 'name' => 'Rejected', 'created_at' => now(), 'updated_at' => now()],
        ];

        for ($i = 1; count($records) < 50; $i++) {
            $code = 'status_'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $records[] = [
                'code' => $code,
                'name' => ucfirst($faker->unique()->words(2, true)),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        ProposalStatus::insert($records);
    }
}
