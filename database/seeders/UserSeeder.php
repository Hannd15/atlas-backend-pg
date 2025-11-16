<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->delete();

        User::factory()->create([
            'name' => 'Administrador Demo',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        User::factory()->count(59)->create();
    }
}
