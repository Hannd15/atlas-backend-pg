<?php

namespace Database\Seeders;

use App\Models\File;
use App\Models\RepositoryProposal;
use App\Models\RepositoryProposalFile;
use Illuminate\Database\Seeder;

class RepositoryProposalFileSeeder extends Seeder
{
    public function run(): void
    {
        RepositoryProposalFile::query()->delete();

        $repositoryIds = RepositoryProposal::pluck('id')->all();
        $fileIds = File::pluck('id')->all();

        if (empty($repositoryIds) || empty($fileIds)) {
            return;
        }

        $faker = fake();
        $records = [];
        $used = [];

        while (count($records) < 10) {
            $repo = $faker->randomElement($repositoryIds);
            $file = $faker->randomElement($fileIds);
            $key = $repo.'-'.$file;
            if (isset($used[$key])) {
                continue;
            }
            $used[$key] = true;

            $records[] = [
                'repository_proposal_id' => $repo,
                'file_id' => $file,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        RepositoryProposalFile::insert($records);
    }
}
