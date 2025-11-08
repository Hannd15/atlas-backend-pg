<?php

namespace Database\Seeders;

use App\Models\ProjectPosition;
use Illuminate\Database\Seeder;

class ProjectPositionSeeder extends Seeder
{
    public function run(): void
    {
        ProjectPosition::query()->delete();

        $positions = ['Jurado', 'Asesor', 'Director', 'Codirector'];

        foreach ($positions as $position) {
            ProjectPosition::create([
                'name' => $position,
            ]);
        }
    }
}
