<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliverables', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('name');
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::getConnection()->statement('ALTER TABLE deliverables ALTER COLUMN due_date DROP NOT NULL');
    }

    public function down(): void
    {
        Schema::table('deliverables', function (Blueprint $table): void {
            $table->dropColumn('description');
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::getConnection()->statement('ALTER TABLE deliverables ALTER COLUMN due_date SET NOT NULL');
    }
};
