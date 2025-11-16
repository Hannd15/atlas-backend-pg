<?php

namespace Database\Seeders;

use App\Models\ProjectPosition;
use App\Models\User;
use App\Models\UserProjectEligibility;
use Illuminate\Database\Seeder;

class UserProjectEligibilitySeeder extends Seeder
{
    public function run(): void
    {
        UserProjectEligibility::query()->delete();

        $userIds = User::pluck('id')->all();
        $positionIds = ProjectPosition::pluck('id')->all();

        if (empty($userIds) || empty($positionIds)) {
            return;
        }

        $faker = fake();
        $records = [];
        $used = [];

        $maxCombinations = count($userIds) * count($positionIds);
        $target = min(200, max(10, $maxCombinations / 2));

        while (count($records) < $target && count($used) < $maxCombinations) {
            $combo = [
                $faker->randomElement($userIds),
                $faker->randomElement($positionIds),
            ];
            $key = implode('-', $combo);

            if (isset($used[$key])) {
                continue;
            }

            $used[$key] = true;

            $records[] = [
                'user_id' => $combo[0],
                'project_position_id' => $combo[1],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        UserProjectEligibility::insert($records);
    }
}
