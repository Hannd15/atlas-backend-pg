<?php

namespace Database\Seeders;

use App\Models\AcademicPeriodState;
use Illuminate\Database\Seeder;

class AcademicPeriodStateSeeder extends Seeder
{
    public function run(): void
    {
        $states = [
            ['name' => 'Activo', 'description' => 'El periodo académico está en curso.'],
            ['name' => 'Terminado', 'description' => 'El periodo académico ha finalizado.'],
        ];

        foreach ($states as $state) {
            AcademicPeriodState::query()->firstOrCreate(['name' => $state['name']], $state);
        }
    }
}
