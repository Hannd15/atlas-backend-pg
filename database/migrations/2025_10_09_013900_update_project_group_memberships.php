<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_groups', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
        });

        Schema::table('project_groups', function (Blueprint $table): void {
            $table->unsignedBigInteger('project_id')->nullable()->change();
        });

        Schema::table('project_groups', function (Blueprint $table): void {
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
        });

        Schema::table('group_members', function (Blueprint $table): void {
            $table->unique(['group_id', 'user_id']);
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table): void {
            $table->dropUnique('group_members_group_id_user_id_unique');
            $table->dropUnique('group_members_user_id_unique');
        });

        Schema::table('project_groups', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
        });

        Schema::table('project_groups', function (Blueprint $table): void {
            $table->unsignedBigInteger('project_id')->nullable(false)->change();
        });

        Schema::table('project_groups', function (Blueprint $table): void {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }
};
