<?php

namespace Database\Seeders;

use App\Models\ProjectPosition;
use Illuminate\Database\Seeder;

class ProjectPositionSeeder extends Seeder
{
    public function run(): void
    {
        ProjectPosition::query()->delete();

        $faker = fake();
        $records = [];

        for ($i = 0; $i < 10; $i++) {
            $records[] = [
                'name' => ucfirst($faker->unique()->jobTitle()),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        ProjectPosition::insert($records);
    }
}
