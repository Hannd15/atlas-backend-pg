<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('url')->nullable()->after('observations');
        });

        DB::table('meetings')->orderBy('id')->chunkById(100, function ($meetings): void {
            foreach ($meetings as $meeting) {
                $date = $meeting->meeting_date
                    ? Carbon::parse($meeting->meeting_date)->format('Ymd')
                    : now()->format('Ymd');

                DB::table('meetings')
                    ->where('id', $meeting->id)
                    ->update([
                        'url' => sprintf('https://meetings.test/project-%s/%s', $meeting->project_id, $date),
                    ]);
            }
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->string('url')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('url');
        });
    }
};
