<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubric_thematic_lines', function (Blueprint $table) {
            $table->foreignId('rubric_id')->constrained()->onDelete('cascade');
            $table->foreignId('thematic_line_id')->constrained()->onDelete('cascade');
            $table->primary(['rubric_id', 'thematic_line_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rubric_thematic_lines');
    }
};