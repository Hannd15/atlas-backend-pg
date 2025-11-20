<?php

namespace Database\Seeders;

use App\Models\ProjectStatus;
use Illuminate\Database\Seeder;

class ProjectStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ProjectStatus::query()->delete();

        $faker = fake();
        $records = [];

        $baseStatuses = ['Activo', 'En proceso', 'Terminado'];

        foreach ($baseStatuses as $status) {
            $records[] = [
                'name' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        ProjectStatus::insert($records);
    }
}
