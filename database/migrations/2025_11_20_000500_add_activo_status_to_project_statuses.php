<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('project_statuses')->where('name', 'Activo')->exists();

        if (! $exists) {
            DB::table('project_statuses')->insert([
                'name' => 'Activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('project_statuses')->where('name', 'Activo')->delete();
    }
};
