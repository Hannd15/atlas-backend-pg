<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_proposal_files', function (Blueprint $table) {
            $table->foreignId('repository_proposal_id')->constrained('repository_proposals')->onDelete('cascade');
            $table->foreignId('file_id')->constrained()->onDelete('cascade');
            $table->primary(['repository_proposal_id', 'file_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_proposal_files');
    }
};
