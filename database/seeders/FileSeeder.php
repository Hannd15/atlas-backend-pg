<?php

namespace Database\Seeders;

use App\Models\File;
use Illuminate\Database\Seeder;

class FileSeeder extends Seeder
{
    public function run(): void
    {
        File::query()->delete();

        $faker = fake();
        $records = [];

        for ($i = 0; $i < 10; $i++) {
            $name = $faker->unique()->words(2, true);
            $extension = $faker->fileExtension();
            $records[] = [
                'name' => ucfirst($name),
                'extension' => $extension,
                'url' => $faker->url(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        File::insert($records);
    }
}
