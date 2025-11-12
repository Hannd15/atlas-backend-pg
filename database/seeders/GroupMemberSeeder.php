<?php

namespace Database\Seeders;

use App\Models\GroupMember;
use App\Models\ProjectGroup;
use App\Models\User;
use Illuminate\Database\Seeder;

class GroupMemberSeeder extends Seeder
{
    public function run(): void
    {
        GroupMember::query()->delete();

        $groupIds = ProjectGroup::pluck('id')->all();
        $userIds = User::pluck('id')->all();

        if (empty($groupIds) || empty($userIds)) {
            return;
        }

        $faker = fake();
        $records = [];
        $used = [];

        while (count($records) < 10) {
            $group = $faker->randomElement($groupIds);
            $user = $faker->randomElement($userIds);
            $key = $group.'-'.$user;

            if (isset($used[$key])) {
                continue;
            }

            $used[$key] = true;

            $records[] = [
                'group_id' => $group,
                'user_id' => $user,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        GroupMember::insert($records);
    }
}
