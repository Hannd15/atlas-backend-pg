<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_request_files', function (Blueprint $table) {
            $table->foreignId('approval_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->primary(['approval_request_id', 'file_id']);
            $table->timestamps();
        });

        Schema::table('approval_request_recipients', function (Blueprint $table) {
            $table->text('comment')->nullable()->after('decision');
        });
    }

    public function down(): void
    {
        Schema::table('approval_request_recipients', function (Blueprint $table) {
            $table->dropColumn('comment');
        });

        Schema::dropIfExists('approval_request_files');
    }
};
