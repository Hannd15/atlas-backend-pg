<?php

namespace Database\Seeders;

use App\Models\AcademicPeriodState;
use Illuminate\Database\Seeder;

class AcademicPeriodStateSeeder extends Seeder
{
    public function run(): void
    {
        $states = [
            ['name' => 'Draft', 'description' => 'Academic period is being prepared.'],
            ['name' => 'Active', 'description' => 'Academic period is currently running.'],
            ['name' => 'Closed', 'description' => 'Academic period has ended.'],
        ];

        foreach ($states as $state) {
            AcademicPeriodState::query()->firstOrCreate(['name' => $state['name']], $state);
        }
    }
}
