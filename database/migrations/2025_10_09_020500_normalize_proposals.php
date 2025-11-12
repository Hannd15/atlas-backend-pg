<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('proposal_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('proposal_files', function (Blueprint $table) {
            $table->foreignId('proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->primary(['proposal_id', 'file_id']);
            $table->timestamps();
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->foreignId('proposal_type_id')->nullable()->after('description')->constrained('proposal_types')->nullOnDelete();
            $table->foreignId('proposal_status_id')->nullable()->after('proposal_type_id')->constrained('proposal_statuses')->nullOnDelete();
        });

        DB::table('proposal_types')->insert([
            ['code' => 'made_by_student', 'name' => 'Made by student', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'made_by_teacher', 'name' => 'Made by teacher', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('proposal_statuses')->insert([
            ['code' => 'pending', 'name' => 'Pending', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'approved', 'name' => 'Approved', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'rejected', 'name' => 'Rejected', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $typeIds = DB::table('proposal_types')->pluck('id', 'code');
        $statusIds = DB::table('proposal_statuses')->pluck('id', 'code');

        $defaultTypeId = $typeIds['made_by_student'] ?? null;
        $defaultStatusId = $statusIds['pending'] ?? null;

        DB::table('proposals')->select('id', 'type', 'status')->orderBy('id')->get()->each(function ($proposal) use ($typeIds, $statusIds, $defaultTypeId, $defaultStatusId) {
            $typeId = $typeIds[$proposal->type] ?? $defaultTypeId;
            $statusId = $statusIds[$proposal->status] ?? $defaultStatusId;

            DB::table('proposals')->where('id', $proposal->id)->update([
                'proposal_type_id' => $typeId,
                'proposal_status_id' => $statusId,
            ]);
        });

        Schema::table('proposals', function (Blueprint $table) {
            if (Schema::hasColumn('proposals', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('proposals', 'status')) {
                $table->dropColumn('status');
            }
        });

        Schema::dropIfExists('repository_proposal_files');
        Schema::dropIfExists('repository_proposals');
    }

    public function down(): void
    {
        Schema::create('repository_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('repository_proposal_files', function (Blueprint $table) {
            $table->foreignId('repository_proposal_id')->constrained('repository_proposals')->cascadeOnDelete();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->primary(['repository_proposal_id', 'file_id']);
            $table->timestamps();
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->string('status')->default('pending');
            $table->string('type')->nullable();
        });

        DB::table('proposals')->select('id', 'proposal_type_id', 'proposal_status_id')->orderBy('id')->get()->each(function ($proposal) {
            $typeCode = DB::table('proposal_types')->where('id', $proposal->proposal_type_id)->value('code');
            $statusCode = DB::table('proposal_statuses')->where('id', $proposal->proposal_status_id)->value('code');

            DB::table('proposals')->where('id', $proposal->id)->update([
                'type' => $typeCode,
                'status' => $statusCode ?? 'pending',
            ]);
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proposal_status_id');
            $table->dropConstrainedForeignId('proposal_type_id');
        });

        Schema::dropIfExists('proposal_files');
        Schema::dropIfExists('proposal_statuses');
        Schema::dropIfExists('proposal_types');
    }
};
