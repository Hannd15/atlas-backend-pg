<?php

use App\Models\AcademicPeriodState;
use App\Models\Phase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('phase_id')->nullable()->after('proposal_id')->constrained()->nullOnDelete();
        });

        $activePhaseId = Phase::query()
            ->whereHas('period', fn ($query) => $query->where('state_id', AcademicPeriodState::activeId()))
            ->orderBy('start_date')
            ->value('id');

        if ($activePhaseId) {
            DB::table('projects')->whereNull('phase_id')->update(['phase_id' => $activePhaseId]);
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('phase_id');
        });
    }
};
