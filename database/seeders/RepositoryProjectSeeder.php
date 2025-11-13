<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\RepositoryProject;
use Illuminate\Database\Seeder;

class RepositoryProjectSeeder extends Seeder
{
    public function run(): void
    {
        RepositoryProject::query()->delete();

        $projectIds = Project::pluck('id')->all();
        if (empty($projectIds)) {
            return;
        }

        $faker = fake();
        $records = [];

        for ($i = 0; $i < 10; $i++) {
            $records[] = [
                'project_id' => $faker->optional()->randomElement($projectIds),
                'title' => 'Repository Project '.strtoupper($faker->unique()->bothify('##??')),
                'description' => $faker->paragraph(),
                'publish_date' => $faker->dateTimeBetween('-2 years', 'now'),
                'keywords_es' => implode(', ', $faker->words(3)),
                'keywords_en' => implode(', ', $faker->words(3)),
                'abstract_es' => $faker->paragraph(3),
                'abstract_en' => $faker->paragraph(3),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        RepositoryProject::insert($records);
    }
}
