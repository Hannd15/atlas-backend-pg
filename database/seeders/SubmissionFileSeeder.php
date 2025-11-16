<?php

namespace Database\Seeders;

use App\Models\File;
use App\Models\Submission;
use App\Models\SubmissionFile;
use Illuminate\Database\Seeder;

class SubmissionFileSeeder extends Seeder
{
    public function run(): void
    {
        SubmissionFile::query()->delete();

        $submissionIds = Submission::pluck('id')->all();
        $fileIds = File::pluck('id')->all();

        if (empty($submissionIds) || empty($fileIds)) {
            return;
        }

        $faker = fake();
        $records = [];
        $used = [];

        $maxCombinations = count($submissionIds) * count($fileIds);
        $target = min(200, max(10, $maxCombinations));

        while (count($records) < $target && count($used) < $maxCombinations) {
            $submission = $faker->randomElement($submissionIds);
            $file = $faker->randomElement($fileIds);
            $key = $submission.'-'.$file;

            if (isset($used[$key])) {
                continue;
            }

            $used[$key] = true;

            $records[] = [
                'submission_id' => $submission,
                'file_id' => $file,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        SubmissionFile::insert($records);
    }
}
