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

        while (count($records) < 10) {
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
