<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
            $table->foreignId('thematic_line_id')->nullable()->after('description')->constrained()->nullOnDelete();
        });

        DB::table('projects')
            ->whereNotNull('proposal_id')
            ->orderBy('id')
            ->chunkById(100, function ($projects): void {
                foreach ($projects as $project) {
                    $proposal = DB::table('proposals')
                        ->select('description', 'thematic_line_id')
                        ->where('id', $project->proposal_id)
                        ->first();

                    if (! $proposal) {
                        continue;
                    }

                    $updates = [];

                    if ($project->description === null && $proposal->description !== null) {
                        $updates['description'] = $proposal->description;
                    }

                    if ($project->thematic_line_id === null && $proposal->thematic_line_id !== null) {
                        $updates['thematic_line_id'] = $proposal->thematic_line_id;
                    }

                    if (! empty($updates)) {
                        DB::table('projects')->where('id', $project->id)->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['thematic_line_id']);
            $table->dropColumn(['description', 'thematic_line_id']);
        });
    }
};
