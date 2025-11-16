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

        $maxMembers = min(50, count($userIds));
        $selectedUsers = collect($userIds)->shuffle()->take($maxMembers);

        foreach ($selectedUsers as $userId) {
            $records[] = [
                'group_id' => $faker->randomElement($groupIds),
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        GroupMember::insert($records);
    }
}
