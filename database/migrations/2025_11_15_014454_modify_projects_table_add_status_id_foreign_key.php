<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Add the new status_id column
            $table->foreignId('status_id')->nullable()->after('status')->constrained('project_statuses')->onDelete('restrict');
        });

        // Migrate data from old 'status' column to the new 'status_id' column
        DB::statement('
            UPDATE projects
            SET status_id = (
                SELECT id FROM project_statuses
                WHERE project_statuses.name = projects.status
                LIMIT 1
            )
            WHERE status IN (SELECT name FROM project_statuses)
        ');

        // Make status_id NOT NULL after migration
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('status_id')->nullable(false)->change();
        });

        // Drop the old status column
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Add back the old status column
            $table->string('status')->after('status_id');
        });

        // Migrate data back from status_id to status column
        DB::statement('
            UPDATE projects
            SET status = (
                SELECT name FROM project_statuses
                WHERE project_statuses.id = projects.status_id
                LIMIT 1
            )
        ');

        // Drop the foreign key and status_id column
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropColumn('status_id');
        });
    }
};
