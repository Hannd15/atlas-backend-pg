<?php

namespace Database\Seeders;

use App\Models\AcademicPeriodState;
use Illuminate\Database\Seeder;

class AcademicPeriodStateSeeder extends Seeder
{
    public function run(): void
    {
        AcademicPeriodState::query()->delete();

        $faker = fake();
        $records = [];

        $baseStates = [
            ['name' => AcademicPeriodState::NAME_ACTIVO, 'description' => 'El periodo académico está en curso.'],
            ['name' => AcademicPeriodState::NAME_TERMINADO, 'description' => 'El periodo académico ha finalizado.'],
        ];

        foreach ($baseStates as $state) {
            $records[] = [
                'name' => $state['name'],
                'description' => $state['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        while (count($records) < 50) {
            $records[] = [
                'name' => 'Estado '.$faker->unique()->lexify('?????'),
                'description' => $faker->sentence(8),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        AcademicPeriodState::insert($records);
    }
}
