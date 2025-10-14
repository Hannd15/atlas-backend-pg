<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->delete();

        $password = bcrypt('password');

        $users = [
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => $password,
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        $faker = fake();
        $faker->unique(true);

        while (count($users) < 10) {
            $users[] = [
                'name' => $faker->unique()->name(),
                'email' => $faker->unique()->safeEmail(),
                'password' => $password,
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        User::insert($users);
    }
}
