<?php

namespace Database\Seeders;

use App\Models\File;
use App\Models\Proposal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProposalFileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('proposal_files')->delete();

        $proposalIds = Proposal::pluck('id')->all();
        $fileIds = File::pluck('id')->all();

        if (empty($proposalIds) || empty($fileIds)) {
            return;
        }

        $faker = fake();
        $records = [];
        $used = [];

        $maxCombinations = count($proposalIds) * count($fileIds);
        $target = min(200, max(10, count($proposalIds) * 3));

        while (count($records) < $target && count($used) < $maxCombinations) {
            $proposal = $faker->randomElement($proposalIds);
            $file = $faker->randomElement($fileIds);
            $key = $proposal.'-'.$file;

            if (isset($used[$key])) {
                continue;
            }

            $used[$key] = true;

            $records[] = [
                'proposal_id' => $proposal,
                'file_id' => $file,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($records)) {
            DB::table('proposal_files')->insert($records);
        }
    }
}
