<?php

namespace Database\Seeders;

use App\Models\Deliverable;
use App\Models\DeliverableFile;
use App\Models\File;
use Illuminate\Database\Seeder;

class DeliverableFileSeeder extends Seeder
{
    public function run(): void
    {
        DeliverableFile::query()->delete();

        $deliverableIds = Deliverable::pluck('id')->all();
        $fileIds = File::pluck('id')->all();

        if (empty($deliverableIds) || empty($fileIds)) {
            return;
        }

        $faker = fake();
        $records = [];
        $used = [];

        while (count($records) < 10) {
            $deliverable = $faker->randomElement($deliverableIds);
            $file = $faker->randomElement($fileIds);
            $key = $deliverable . '-' . $file;

            if (isset($used[$key])) {
                continue;
            }

            $used[$key] = true;

            $records[] = [
                'deliverable_id' => $deliverable,
                'file_id' => $file,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DeliverableFile::insert($records);
    }
}
