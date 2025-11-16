<?php

namespace Database\Seeders;

use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Models\User;
use Illuminate\Database\Seeder;

class MeetingAttendeeSeeder extends Seeder
{
    public function run(): void
    {
        MeetingAttendee::query()->delete();

        $meetingIds = Meeting::pluck('id')->all();
        $userIds = User::pluck('id')->all();

        if (empty($meetingIds) || empty($userIds)) {
            return;
        }

        $faker = fake();
        $records = [];
        $used = [];

        $maxCombinations = count($meetingIds) * count($userIds);
        $target = min(200, max(10, $maxCombinations));

        while (count($records) < $target && count($used) < $maxCombinations) {
            $meeting = $faker->randomElement($meetingIds);
            $user = $faker->randomElement($userIds);
            $key = $meeting.'-'.$user;

            if (isset($used[$key])) {
                continue;
            }
            $used[$key] = true;

            $records[] = [
                'meeting_id' => $meeting,
                'user_id' => $user,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        MeetingAttendee::insert($records);
    }
}
